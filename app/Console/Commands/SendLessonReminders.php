<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Notifications\LessonReminder;
use Illuminate\Console\Command;

class SendLessonReminders extends Command
{
    protected $signature = 'studylikepro:send-lesson-reminders';

    protected $description = 'Queue day-ahead and one-hour reminders for confirmed lessons';

    public function handle(): int
    {
        $finalLead = (int) config('studylikepro.booking.reminder_lead_minutes');

        // A lesson already inside the final window gets only the final reminder;
        // the day-ahead one is for lessons that are further out.
        $dayAhead = $this->remind(
            leadMinutes: LessonReminder::DAY_AHEAD_MINUTES,
            marker: 'reminder_day_sent_at',
            notBeforeMinutes: $finalLead,
        );

        $shortly = $this->remind(
            leadMinutes: $finalLead,
            marker: 'reminder_sent_at',
        );

        $this->info("Queued {$dayAhead} day-ahead and {$shortly} final reminder(s).");

        return self::SUCCESS;
    }

    /**
     * Send the reminder for one window: every confirmed lesson starting inside
     * the lead time that has not had this reminder yet.
     */
    private function remind(int $leadMinutes, string $marker, int $notBeforeMinutes = 0): int
    {
        $due = Booking::query()
            ->where('status', BookingStatus::Confirmed->value)
            ->whereNull($marker)
            ->whereBetween('starts_at', [now()->addMinutes($notBeforeMinutes), now()->addMinutes($leadMinutes)])
            ->with(['student', 'teacherProfile.user'])
            ->get();

        foreach ($due as $booking) {
            $booking->student->notify(new LessonReminder($booking, $leadMinutes));
            $booking->teacherProfile->user->notify(new LessonReminder($booking, $leadMinutes));

            $booking->forceFill([$marker => now()])->save();
        }

        return $due->count();
    }
}
