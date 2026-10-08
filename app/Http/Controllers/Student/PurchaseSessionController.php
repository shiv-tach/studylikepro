<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePurchaseSessionRequest;
use App\Models\Booking;
use App\Models\ServicePurchase;
use App\Services\BookingTransitionService;
use App\Services\SessionSchedulingService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Booking sessions against an already-paid purchase: one or several customised
 * sessions for a lesson plan, a single Q&A or paper-discussion session.
 */
class PurchaseSessionController extends Controller
{
    public function __construct(
        private readonly SessionSchedulingService $sessions,
        private readonly BookingTransitionService $transitions,
    ) {}

    public function create(Request $request, ServicePurchase $purchase): View
    {
        Gate::authorize('scheduleSession', $purchase);

        if (! $purchase->type()->isLiveSession()) {
            abort(404);
        }

        $purchase->load(['teacherProfile.user', 'subject', 'lesson', 'offering']);

        $durations = $this->durationOptions($purchase);

        $filters = $request->validate([
            'duration_minutes' => ['nullable', 'integer'],
        ]);

        $duration = $filters['duration_minutes'] ?? ($durations[0] ?? 60);

        $slots = $this->sessions->availableSlots($purchase, $duration);

        return view('student.purchases.session-create', [
            'purchase' => $purchase,
            'durations' => $durations,
            'duration' => $duration,
            'slots' => $slots,
            'timezone' => config('studylikepro.default_display_timezone'),
        ]);
    }

    public function store(StorePurchaseSessionRequest $request, ServicePurchase $purchase): RedirectResponse
    {
        Gate::authorize('scheduleSession', $purchase);

        $duration = (int) $request->validated('duration_minutes');
        $startsAt = Carbon::parse((string) $request->validated('starts_at'));

        $booking = $this->sessions->reserve($purchase, $startsAt, $duration);

        return redirect()
            ->route('student.bookings.show', $booking)
            ->with('status', 'session-booked');
    }

    public function cancel(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless($booking->isPurchaseSession(), 404);

        Gate::authorize('cancel', $booking);

        $this->transitions->cancel(
            $booking,
            Booking::CANCELLED_BY_STUDENT,
            (string) $request->input('reason', ''),
        );

        return redirect()
            ->route('student.purchases.show', $booking->service_purchase_id)
            ->with('status', 'session-cancelled');
    }

    /**
     * Allowed session lengths: the offering default plus the standard set,
     * clamped to the plan's remaining minutes when it is a lesson plan.
     *
     * @return list<int>
     */
    private function durationOptions(ServicePurchase $purchase): array
    {
        $durations = array_map('intval', (array) config('studylikepro.lesson_durations'));

        $default = $this->sessions->defaultSessionMinutes($purchase);

        $durations = array_values(array_unique([$default, ...$durations]));
        sort($durations);

        if ($purchase->isLessonPlan()) {
            $remaining = $purchase->remainingMinutes();

            $durations = array_values(array_filter($durations, fn (int $minutes) => $minutes <= $remaining));

            if ($durations === []) {
                $durations = [$remaining];
            }
        }

        return $durations;
    }
}
