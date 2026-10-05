<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Events\ReviewChanged;
use App\Models\Booking;
use App\Models\Review;
use App\Models\User;
use App\Notifications\ReviewFlagged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * The trust loop: students review delivered lessons, teachers can ask support to
 * step in, and the teacher's public numbers follow.
 */
class ReviewService
{
    /**
     * Write the review for a completed lesson.
     */
    public function submit(Booking $booking, User $student, int $rating, ?string $comment): Review
    {
        if ($booking->status !== BookingStatus::Completed) {
            throw ValidationException::withMessages([
                'rating' => __('You can review a lesson once it has been delivered.'),
            ]);
        }

        if ($booking->review()->exists()) {
            throw ValidationException::withMessages([
                'rating' => __('You have already reviewed this lesson — you can edit it for a week.'),
            ]);
        }

        $review = DB::transaction(fn () => Review::query()->create([
            'booking_id' => $booking->id,
            'student_id' => $student->id,
            'teacher_profile_id' => $booking->teacher_profile_id,
            'rating' => $rating,
            'comment' => $comment,
        ]));

        ReviewChanged::dispatch($review);

        return $review;
    }

    /**
     * Change an existing review while the edit window is open.
     */
    public function update(Review $review, int $rating, ?string $comment): Review
    {
        if (! $review->isEditable()) {
            throw ValidationException::withMessages([
                'rating' => __('The :days-day edit window for this review has closed.', [
                    'days' => config('studylikepro.reviews.edit_window_days'),
                ]),
            ]);
        }

        $review->forceFill([
            'rating' => $rating,
            'comment' => $comment,
            'edited_at' => now(),
        ])->save();

        ReviewChanged::dispatch($review);

        return $review;
    }

    /**
     * The teacher reports a review; support picks it up from the moderation queue.
     */
    public function flag(Review $review, User $teacher, string $reason, ?string $notes = null): Review
    {
        $review->forceFill([
            'flagged_at' => now(),
            'flagged_by' => $teacher->id,
            'flag_reason' => $reason,
            'flag_notes' => $notes,
        ])->save();

        Notification::send(
            User::query()->role(User::ROLE_ADMIN)->get(),
            new ReviewFlagged($review),
        );

        return $review;
    }

    /**
     * Take a review off the public profile without deleting it (support action,
     * surfaced in the admin console).
     */
    public function hide(Review $review, ?User $by = null): Review
    {
        $review->forceFill([
            'hidden_at' => now(),
            'hidden_by' => $by?->id,
            // The report is settled by hiding the review, so it stops appearing
            // in any queue.
            'flagged_at' => null,
            'flagged_by' => null,
            'flag_reason' => null,
            'flag_notes' => null,
        ])->save();

        ReviewChanged::dispatch($review);

        return $review;
    }
}
