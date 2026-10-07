<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\CancelBookingRequest;
use App\Models\Booking;
use App\Models\TeacherProfile;
use App\Services\BookingTransitionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function __construct(private readonly BookingTransitionService $transitions) {}

    /**
     * The teaching schedule: live lessons, unpaid holds and recent history.
     */
    public function index(Request $request): View
    {
        $teacher = $this->teacherProfile($request);

        $upcoming = $teacher->bookings()
            ->whereIn('status', [BookingStatus::Confirmed->value, BookingStatus::InProgress->value])
            ->where('starts_at', '>=', now()->subHour())
            ->with(['student', 'subject', 'lesson'])
            ->oldest('starts_at')
            ->limit(20)
            ->get();

        $holds = $teacher->bookings()
            ->where('status', BookingStatus::PendingPayment->value)
            ->where('expires_at', '>', now())
            ->with(['student', 'subject', 'lesson'])
            ->oldest('starts_at')
            ->get();

        $past = $teacher->bookings()
            ->past()
            ->with(['student', 'subject', 'lesson'])
            ->latest('starts_at')
            ->limit(10)
            ->get();

        return view('teacher.schedule.index', [
            'teacher' => $teacher,
            'upcoming' => $upcoming,
            'holds' => $holds,
            'past' => $past,
            'timezone' => $teacher->timezone ?: config('studylikepro.default_display_timezone'),
        ]);
    }

    public function show(Request $request, Booking $booking): View
    {
        Gate::authorize('view', $booking);

        $teacher = $this->teacherProfile($request);

        abort_unless($booking->teacher_profile_id === $teacher->id || $request->user()->isAdmin(), 403);

        $booking->load(['student.studentProfile', 'subject', 'lesson', 'tutoringRequest', 'conversation']);

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

        return redirect()
            ->route('teacher.bookings.show', $booking)
            ->with('status', 'booking-'.$action);
    }

    private function teacherProfile(Request $request): TeacherProfile
    {
        return $request->user()->teacherProfile()->firstOrFail();
    }
}
