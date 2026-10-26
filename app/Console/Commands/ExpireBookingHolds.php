<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Services\BookingTransitionService;
use Illuminate\Console\Command;

class ExpireBookingHolds extends Command
{
    protected $signature = 'studylikepro:expire-holds';

    protected $description = 'Release unpaid booking holds whose payment window lapsed';

    public function handle(BookingTransitionService $transitions): int
    {
        $lapsed = Booking::query()
            ->where('status', BookingStatus::PendingPayment->value)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            // A submitted slip pauses the clock: an admin still has to check it.
            ->whereDoesntHave('payments', fn ($query) => $query
                ->where('status', PaymentStatus::Submitted->value))
            ->get();

        foreach ($lapsed as $booking) {
            $transitions->expire($booking);
        }

        $this->info("Expired {$lapsed->count()} unpaid booking hold(s).");

        return self::SUCCESS;
    }
}
