<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;
use App\Notifications\DailyDigest;
use App\Services\ConversationService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * The morning summary: lessons on today's timetable plus anything waiting in
 * the app. Skipped for users who turned email off or have nothing to report.
 */
class SendDailyDigest extends Command
{
    protected $signature = 'studylikepro:send-daily-digest';

    protected $description = 'Email each user a summary of today\'s lessons and unread activity';

    public function handle(ConversationService $conversations): int
    {
        $sent = 0;

        User::query()
            ->whereHas('roles', fn ($query) => $query->whereIn('name', [User::ROLE_STUDENT, User::ROLE_TEACHER]))
            ->with(['studentProfile', 'teacherProfile'])
            ->chunkById(100, function ($users) use ($conversations, &$sent) {
                foreach ($users as $user) {
                    if (! $user->wantsEmailNotifications()) {
                        continue;
                    }

                    $lessons = $this->lessonsFor($user);
                    $unreadMessages = $conversations->unreadCountFor($user);
                    $unreadNotifications = $user->unreadNotifications()->count();

                    if ($lessons === [] && $unreadMessages === 0) {
                        continue;
                    }

                    $user->notify(new DailyDigest($lessons, $unreadMessages, $unreadNotifications));
                    $sent++;
                }
            });

        $this->info("Sent {$sent} daily digest(s).");

        return self::SUCCESS;
    }

    /**
     * Today's confirmed lessons in the user's own timezone.
     *
     * @return list<array{title: string, detail: string}>
     */
    private function lessonsFor(User $user): array
    {
        $timezone = $user->studentProfile?->timezone
            ?? $user->teacherProfile?->timezone
            ?? config('studylikepro.default_display_timezone');

        $today = Carbon::now($timezone);

        $query = Booking::query()
            ->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::InProgress->value])
            ->whereBetween('starts_at', [
                $today->copy()->startOfDay()->utc(),
                $today->copy()->endOfDay()->utc(),
            ])
            ->with(['subject', 'lesson', 'teacherProfile.user', 'student'])
            ->orderBy('starts_at');

        if ($user->isTeacher()) {
            $query->where('teacher_profile_id', $user->teacherProfile?->id ?? 0);
        } else {
            $query->where('student_id', $user->id);
        }

        return $query->get()->map(function (Booking $booking) use ($user, $timezone, $today) {
            $startsAt = $booking->starts_at->copy()->setTimezone($timezone);

            $lesson = $booking->lesson?->name ?? $booking->subject?->name ?? 'Tutoring lesson';
            $with = $user->isTeacher()
                ? ($booking->learner_name ?: $booking->student->name)
                : $booking->teacherProfile->user->name;

            // "in 3 hours" is friendlier than a bare clock time in a morning email.
            $hoursAway = max(0, (int) round($today->diffInHours($startsAt, false)));

            return [
                'title' => sprintf('%.0f minute%s %s with %s', $booking->durationMinutes(), $booking->durationMinutes() === 1 ? '' : 's', $lesson, $with),
                'detail' => $startsAt->format('H:i').($hoursAway > 0 ? ' (in about '.$hoursAway.' hour'.($hoursAway === 1 ? '' : 's').')' : ' (now)'),
            ];
        })->values()->all();
    }
}
