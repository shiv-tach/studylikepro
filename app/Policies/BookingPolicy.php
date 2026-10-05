<?php

namespace App\Policies;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Services\PlatformSettings;

class BookingPolicy
{
    public function __construct(private readonly PlatformSettings $settings) {}

    public function view(User $user, Booking $booking): bool
    {
        return $user->isAdmin()
            || $booking->student_id === $user->id
            || $booking->teacherProfile?->user_id === $user->id;
    }

    /**
     * Start a lesson with this teacher (student side).
     */
    public function create(User $user, TeacherProfile $teacher): bool
    {
        return $user->isStudent() && $teacher->isApproved();
    }

    public function pay(User $user, Booking $booking): bool
    {
        return $booking->student_id === $user->id
            && $booking->status === BookingStatus::PendingPayment;
    }

    public function cancel(User $user, Booking $booking): bool
    {
        if ($user->isAdmin()) {
            return ! $booking->status->isFinal();
        }

        if ($booking->student_id === $user->id) {
            return $this->studentCanCancel($booking);
        }

        if ($booking->teacherProfile?->user_id === $user->id) {
            return $booking->status->holdsSlot();
        }

        return false;
    }

    /**
     * Enter the live classroom. Participants only — admins inspect, they do not join.
     */
    public function join(User $user, Booking $booking): bool
    {
        return $booking->student_id === $user->id
            || $booking->teacherProfile?->user_id === $user->id;
    }

    /**
     * (Re)open the classroom: the teacher running it, or support fixing a failed room.
     */
    public function provision(User $user, Booking $booking): bool
    {
        return $user->isAdmin()
            || $booking->teacherProfile?->user_id === $user->id;
    }

    public function start(User $user, Booking $booking): bool
    {
        return $booking->teacherProfile?->user_id === $user->id
            && $booking->status === BookingStatus::Confirmed;
    }

    public function complete(User $user, Booking $booking): bool
    {
        return $booking->teacherProfile?->user_id === $user->id
            && $booking->status->isLive();
    }

    public function markNoShow(User $user, Booking $booking): bool
    {
        return $booking->teacherProfile?->user_id === $user->id
            && $booking->status === BookingStatus::Confirmed;
    }

    /**
     * Students may always drop a hold; a confirmed lesson can only be released
     * outside the cancellation window (inside it, support handles it).
     */
    public function studentCanCancel(Booking $booking): bool
    {
        if ($booking->status === BookingStatus::PendingPayment) {
            return true;
        }

        if ($booking->status !== BookingStatus::Confirmed) {
            return false;
        }

        return ! $booking->isInsideStudentCancelWindow($this->settings->int('student_cancel_window_hours'));
    }
}
