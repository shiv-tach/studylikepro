<?php

namespace App\Http\Controllers;

use App\Enums\MeetingStatus;
use App\Models\Booking;
use App\Services\Meetings\MeetingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use Throwable;

/**
 * The live classroom: a shared join page for the student and the teacher with
 * the JOIN window, a countdown before it and a closed notice afterwards.
 */
class ClassroomController extends Controller
{
    public function __construct(private readonly MeetingService $meetings) {}

    public function show(Request $request, Booking $booking): View
    {
        Gate::authorize('join', $booking);

        $booking->load(['teacherProfile.user', 'student', 'subject', 'lesson']);

        // Self-heal a room that never got created (the queue was down when
        // payment landed). Only worth doing close to the lesson, and never over
        // the top of a recorded failure — the teacher/admin retry covers that.
        if ($this->shouldSelfHeal($booking)) {
            try {
                $this->meetings->provision($booking);
            } catch (Throwable) {
                // The failure is recorded on the booking; the page explains it.
            }
        }

        $user = $request->user();

        if ($booking->canJoinNow()) {
            $this->meetings->markJoined($booking);
        }

        return view('bookings.classroom', [
            'booking' => $booking,
            'isTeacher' => $booking->teacherProfile?->user_id === $user->id,
            'joinUrl' => $booking->canJoinNow() ? $booking->meetingUrlFor($user) : null,
            'secondsUntilOpen' => max(0, (int) now()->diffInSeconds($booking->joinOpensAt(), false)),
            'timezone' => $user->studentProfile?->timezone
                ?? $user->teacherProfile?->timezone
                ?? config('studylikepro.default_display_timezone'),
        ]);
    }

    /**
     * Force a fresh room — the teacher before a lesson, or an admin repairing a
     * failed link from the bookings console.
     */
    public function retry(Request $request, Booking $booking): RedirectResponse
    {
        Gate::authorize('provision', $booking);

        try {
            $this->meetings->provision($booking, force: true);
        } catch (Throwable $exception) {
            return back()->withErrors([
                'meeting' => __('We could not open the classroom: :reason', ['reason' => $exception->getMessage()]),
            ]);
        }

        return back()->with('status', 'classroom-regenerated');
    }

    private function shouldSelfHeal(Booking $booking): bool
    {
        return $booking->status->isLive()
            && ! $booking->meetingIsReady()
            && $booking->meeting_status !== MeetingStatus::Failed
            && now()->greaterThan($booking->joinOpensAt()->subMinutes(5));
    }
}
