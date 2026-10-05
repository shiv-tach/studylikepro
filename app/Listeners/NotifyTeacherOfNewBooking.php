<?php

namespace App\Listeners;

use App\Events\BookingCreated;
use App\Notifications\BookingRequestReceived;

class NotifyTeacherOfNewBooking
{
    public function handle(BookingCreated $event): void
    {
        $booking = $event->booking;

        // Holds born from an accepted tutoring request already notified the other
        // side: the teacher proposed the slot themselves, so nothing is "new".
        if ($booking->tutoring_request_id !== null) {
            return;
        }

        $booking->teacherProfile->user->notify(new BookingRequestReceived($booking));
    }
}
