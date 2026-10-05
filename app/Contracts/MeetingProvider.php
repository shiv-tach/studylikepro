<?php

namespace App\Contracts;

use App\Exceptions\MeetingProvisioningException;
use App\Models\Booking;
use App\Support\Meetings\MeetingRoom;

interface MeetingProvider
{
    /**
     * Short identifier stored on bookings.
     */
    public function name(): string;

    /**
     * Open a private room for the lesson and mint the two participant links.
     *
     * @throws MeetingProvisioningException
     */
    public function createRoom(Booking $booking): MeetingRoom;

    /**
     * Best-effort teardown once the lesson is over; never throws.
     */
    public function endRoom(Booking $booking): void;
}
