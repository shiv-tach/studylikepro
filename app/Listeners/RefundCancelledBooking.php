<?php

namespace App\Listeners;

use App\Events\BookingCancelled;
use App\Services\Payments\RefundService;

/**
 * Money follows the cancellation policy: a paid lesson that gets cancelled is
 * refunded without anyone having to remember to do it.
 */
class RefundCancelledBooking
{
    public function __construct(private readonly RefundService $refunds) {}

    public function handle(BookingCancelled $event): void
    {
        $this->refunds->refundForCancellation(
            $event->booking,
            $event->by,
            $event->reason,
        );
    }
}
