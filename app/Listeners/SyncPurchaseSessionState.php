<?php

namespace App\Listeners;

use App\Events\BookingCancelled;
use App\Events\BookingCompleted;
use App\Services\PurchaseService;

/**
 * Keeps a service purchase in step with its sessions: completing a session
 * consumes plan time (or delivers a single-session service), cancelling one
 * returns its time to the plan balance.
 */
class SyncPurchaseSessionState
{
    public function __construct(private readonly PurchaseService $purchases) {}

    public function handle(BookingCompleted|BookingCancelled $event): void
    {
        $purchase = $event->booking->servicePurchase;

        if ($purchase === null) {
            return;
        }

        $this->purchases->syncDelivery($purchase);
    }
}
