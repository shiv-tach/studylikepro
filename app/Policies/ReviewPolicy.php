<?php

namespace App\Policies;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Review;
use App\Models\User;

class ReviewPolicy
{
    /**
     * Only the student of a delivered lesson may review it, and only once.
     */
    public function create(User $user, Booking $booking): bool
    {
        return $booking->student_id === $user->id
            && $booking->status === BookingStatus::Completed
            && $booking->review === null;
    }

    /**
     * The author may edit their own review for a week.
     */
    public function update(User $user, Review $review): bool
    {
        return $review->student_id === $user->id && $review->isEditable();
    }

    /**
     * The teacher can ask support to look at a review; support can hide it.
     */
    public function flag(User $user, Review $review): bool
    {
        return $review->teacherProfile?->user_id === $user->id
            && $review->isVisible()
            && $review->flagged_at === null;
    }

    public function hide(User $user, Review $review): bool
    {
        return $user->isAdmin() && $review->isVisible();
    }
}
