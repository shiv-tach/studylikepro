<?php

namespace App\Listeners;

use App\Events\BookingConfirmed;
use App\Notifications\BookingConfirmed as BookingConfirmedNotification;

class NotifyPartiesOfConfirmedBooking
{
    public function handle(BookingConfirmed $event): void
    {
        $booking = $event->booking;

        $booking->student->notify(new BookingConfirmedNotification($booking));
        $booking->teacherProfile->user->notify(new BookingConfirmedNotification($booking));
    }
}
