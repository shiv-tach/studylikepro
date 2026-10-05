<?php

namespace App\Listeners;

use App\Enums\MeetingStatus;
use App\Events\BookingConfirmed;
use App\Jobs\CreateBookingMeeting;

class ProvisionBookingClassroom
{
    public function handle(BookingConfirmed $event): void
    {
        $booking = $event->booking;

        // Flag the room as being prepared so a stalled queue is visible, then
        // hand the actual provider call to a retrying job.
        if ($booking->meeting_status === null) {
            $booking->forceFill(['meeting_status' => MeetingStatus::Pending])->save();
        }

        CreateBookingMeeting::dispatch($booking->id);
    }
}
