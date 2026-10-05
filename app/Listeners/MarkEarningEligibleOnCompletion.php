<?php

namespace App\Listeners;

use App\Events\BookingCompleted;
use App\Services\Payments\EarningsService;

class MarkEarningEligibleOnCompletion
{
    public function __construct(private readonly EarningsService $earnings) {}

    public function handle(BookingCompleted $event): void
    {
        $this->earnings->markEligibleForBooking($event->booking);
    }
}
