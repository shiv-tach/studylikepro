<?php

namespace App\Listeners;

use App\Events\BookingCreated;
use App\Notifications\BookingRequestReceived;

class NotifyTeacherOfNewBooking
{
    public function handle(BookingCreated $event): void
    {
        $booking = $event->booking;

        $booking->teacherProfile->user->notify(new BookingRequestReceived($booking));
    }
}
