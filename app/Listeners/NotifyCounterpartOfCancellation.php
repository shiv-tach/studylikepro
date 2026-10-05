<?php

namespace App\Listeners;

use App\Events\BookingCancelled;
use App\Models\Booking;
use App\Notifications\BookingCancelled as BookingCancelledNotification;

class NotifyCounterpartOfCancellation
{
    public function handle(BookingCancelled $event): void
    {
        $booking = $event->booking;

        $counterpart = $event->by === Booking::CANCELLED_BY_STUDENT
            ? $booking->teacherProfile->user
            : $booking->student;

        $counterpart->notify(new BookingCancelledNotification($booking, $event->by, $event->reason));
    }
}
