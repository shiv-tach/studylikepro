<?php

namespace App\Http\Controllers\Student;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Lesson statuses the student still has ahead of them.
     */
    private const UPCOMING_STATUSES = [
        BookingStatus::PendingPayment,
        BookingStatus::Confirmed,
        BookingStatus::InProgress,
    ];

    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $profile = $user->studentProfile()->with('grade')->first();
        $timezone = $profile?->timezone ?: config('studylikepro.default_display_timezone');

        $upcoming = $user->bookings()->upcoming()
            ->whereIn('status', array_map(fn (BookingStatus $status) => $status->value, self::UPCOMING_STATUSES));

        // `bookings()` sorts newest first, so the next lesson needs its own order.
        $nextLesson = (clone $upcoming)
            ->with(['subject', 'lesson', 'teacherProfile.user'])
            ->reorder('starts_at')
            ->first();

        $awaitingReview = $user->bookings()
            ->where('status', BookingStatus::Completed->value)
            ->whereDoesntHave('review')
            ->with(['subject', 'lesson', 'teacherProfile.user'])
            ->latest('starts_at')
            ->first();

        return view('student.dashboard', [
            'gradeLabel' => $profile?->grade?->label,
            'learningLanguage' => $profile?->learning_language,
            'displayTimezone' => $timezone,
            'themePreset' => $user->theme['preset'],
            'subjectCount' => $user->interestedSubjects()->count(),
            'upcomingLessonCount' => (clone $upcoming)->count(),
            'completedLessonCount' => $user->bookings()->where('status', BookingStatus::Completed->value)->count(),
            'reviewedLessonCount' => $user->bookings()
                ->where('status', BookingStatus::Completed->value)
                ->whereHas('review')
                ->count(),
            'nextLesson' => $nextLesson ? $this->lessonSummary($nextLesson, $timezone) : null,
            'awaitingReview' => $awaitingReview ? [
                ...$this->lessonSummary($awaitingReview, $timezone),
                'reviewUrl' => route('student.reviews.show', $awaitingReview),
            ] : null,
        ]);
    }

    /**
     * The lesson fields the Vue dashboard renders, already formatted in the
     * student's display timezone.
     *
     * @return array<string, mixed>
     */
    private function lessonSummary(Booking $booking, string $timezone): array
    {
        return [
            'title' => collect([$booking->subject?->name, $booking->lesson?->name])
                ->filter()
                ->implode(' · ') ?: __('Tutoring lesson'),
            'teacher' => $booking->teacherProfile?->user?->name,
            'startsAt' => $booking->starts_at->copy()->setTimezone($timezone)->format('D d M, H:i'),
            'endsAt' => $booking->ends_at->copy()->setTimezone($timezone)->format('H:i'),
            'durationMinutes' => $booking->durationMinutes(),
            'status' => $booking->status->value,
            'statusLabel' => $booking->status->label(),
            'url' => route('student.bookings.show', $booking),
            'joinUrl' => $booking->canJoinNow() ? route('classroom.show', $booking) : null,
            'payBy' => $booking->isHold() && $booking->expires_at?->isFuture()
                ? $booking->expires_at->diffForHumans()
                : null,
        ];
    }
}
