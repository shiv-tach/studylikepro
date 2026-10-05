<?php

namespace App\Http\Controllers\Student;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreReviewRequest;
use App\Models\Booking;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/**
 * Reviewing a delivered lesson: the form doubles as the edit screen while the
 * one-week window is open.
 */
class ReviewController extends Controller
{
    public function __construct(private readonly ReviewService $reviews) {}

    public function show(Request $request, Booking $booking): View
    {
        abort_unless($booking->student_id === $request->user()->id, 403);

        $booking->load(['teacherProfile.user', 'subject', 'topic', 'review']);

        return view('student.reviews.form', [
            'booking' => $booking,
            'review' => $booking->review,
            'delivered' => $booking->status === BookingStatus::Completed,
            'editable' => $booking->review?->isEditable() ?? true,
            'editWindowDays' => config('studylikepro.reviews.edit_window_days'),
        ]);
    }

    public function store(StoreReviewRequest $request, Booking $booking): RedirectResponse
    {
        Gate::authorize('create', [Review::class, $booking]);

        $this->reviews->submit(
            $booking,
            $request->user(),
            (int) $request->validated('rating'),
            $request->validated('comment'),
        );

        return redirect()
            ->route('student.bookings.show', $booking)
            ->with('status', 'review-submitted');
    }

    public function update(StoreReviewRequest $request, Booking $booking): RedirectResponse
    {
        $review = $booking->review()->firstOrFail();

        Gate::authorize('update', $review);

        $this->reviews->update(
            $review,
            (int) $request->validated('rating'),
            $request->validated('comment'),
        );

        return redirect()
            ->route('student.bookings.show', $booking)
            ->with('status', 'review-updated');
    }
}
