<?php

namespace App\Listeners;

use App\Events\BookingExpired;
use App\Notifications\BookingExpired as BookingExpiredNotification;

class NotifyStudentOfExpiredHold
{
    public function handle(BookingExpired $event): void
    {
        $event->booking->student->notify(new BookingExpiredNotification($event->booking));
    }
}
