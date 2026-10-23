<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelBookingRequest;
use App\Models\Booking;
use App\Models\TeacherProfile;
use App\Services\BookingTransitionService;
use App\Support\TeacherScheduleConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function __construct(private readonly BookingTransitionService $transitions) {}

    /**
     * The teaching schedule: what is next, what needs an action, the unpaid
     * holds and the recent history. The payload is built by
     * TeacherScheduleConfig so the Vue app receives labels, times, money and
     * URLs it never has to compute itself.
     */
    public function index(Request $request): View
    {
        $teacher = $this->teacherProfile($request);
        $timezone = $teacher->timezone ?: config('studylikepro.default_display_timezone');

        $upcoming = $teacher->bookings()
            ->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::InProgress->value])
            ->where('starts_at', '>=', now()->subHour())
            ->with(['student', 'subject', 'lesson', 'learnerGrade'])
            // bookings() already sorts newest first, so oldest() would only add a
            // second clause: reorder() is what actually puts the next lesson first.
            ->reorder('starts_at')
            ->limit(20)
            ->get();

        $holds = $teacher->bookings()
            ->where('status', BookingStatus::PendingPayment->value)
            ->where('expires_at', '>', now())
            ->with(['student', 'subject', 'lesson', 'learnerGrade'])
            ->reorder('starts_at')
            ->get();

        $past = $teacher->bookings()
            ->past()
            ->with(['student', 'subject', 'lesson', 'learnerGrade'])
            ->reorder('starts_at', 'desc')
            ->limit(10)
            ->get();

        // The next seven days in the teacher's own timezone, converted back to
        // the app timezone before binding (Eloquent binds wall time, not an offset).
        $weekStart = now()
            ->setTimezone($timezone)
            ->startOfDay()
            ->setTimezone(config('app.timezone'));

        $week = $teacher->bookings()
            ->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::InProgress->value])
            ->whereBetween('starts_at', [$weekStart, $weekStart->copy()->addDays(7)])
            ->get(['starts_at', 'ends_at']);

        return view('teacher.schedule.index', [
            'config' => TeacherScheduleConfig::build(
                $timezone,
                $request->user()->theme['preset'] ?? 'classic',
                $upcoming,
                $holds,
                $past,
                $week,
            ),
            'timezone' => $timezone,
            'upcoming' => $upcoming,
            'holds' => $holds,
            'past' => $past,
        ]);
    }

    public function show(Request $request, Booking $booking): View
    {
        Gate::authorize('view', $booking);

        $teacher = $this->teacherProfile($request);

        abort_unless($booking->teacher_profile_id === $teacher->id || $request->user()->isAdmin(), 403);

        $booking->load(['student.studentProfile', 'subject', 'lesson', 'conversation', 'review']);

        return view('teacher.bookings.show', [
            'booking' => $booking,
            'timezone' => $teacher->timezone ?: config('studylikepro.default_display_timezone'),
        ]);
    }

    public function start(Request $request, Booking $booking): RedirectResponse
    {
        return $this->transition($request, $booking, 'start');
    }

    public function complete(Request $request, Booking $booking): RedirectResponse
    {
        return $this->transition($request, $booking, 'complete');
    }

    public function markNoShow(Request $request, Booking $booking): RedirectResponse
    {
        return $this->transition($request, $booking, 'markNoShow');
    }

    public function cancel(CancelBookingRequest $request, Booking $booking): RedirectResponse
    {
        Gate::authorize('cancel', $booking);

        $this->transitions->cancel(
            $booking,
            $request->user()->isAdmin() ? Booking::CANCELLED_BY_ADMIN : Booking::CANCELLED_BY_TEACHER,
            $request->validated('reason'),
        );

        return redirect()
            ->route('teacher.bookings.show', $booking)
            ->with('status', 'booking-cancelled');
    }

    private function transition(Request $request, Booking $booking, string $action): RedirectResponse
    {
        Gate::authorize($action, $booking);

        $this->transitions->{$action}($booking);

        // Inline actions on the schedule ask to come back there; the lesson page
        // stays the default for every other caller.
        $target = $request->input('return') === 'schedule'
            ? route('teacher.schedule.index')
            : route('teacher.bookings.show', $booking);

        return redirect($target)->with('status', 'booking-'.$action);
    }

    private function teacherProfile(Request $request): TeacherProfile
    {
        return $request->user()->teacherProfile()->firstOrFail();
    }
}
