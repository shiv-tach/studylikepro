<?php

namespace App\Listeners;

use App\Events\BookingCompleted;
use App\Notifications\LessonCompleted;

class NotifyPartiesOfCompletion
{
    public function handle(BookingCompleted $event): void
    {
        $booking = $event->booking;

        $booking->student->notify(new LessonCompleted($booking));
        $booking->teacherProfile->user->notify(new LessonCompleted($booking));
    }
}
