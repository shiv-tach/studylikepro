<?php

namespace App\Console\Commands;

use App\Enums\BookingStatus;
use App\Enums\PurchaseStatus;
use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\Refund;
use App\Models\ServicePurchase;
use App\Services\BookingTransitionService;
use App\Services\Payments\RefundService;
use App\Services\PurchaseService;
use Illuminate\Console\Command;

class ExpireServicePurchases extends Command
{
    protected $signature = 'studylikepro:expire-service-purchases';

    protected $description = 'Expire unpaid purchase holds and lesson plans past their deadline, refunding unused time';

    public function handle(
        PurchaseService $purchases,
        RefundService $refunds,
        BookingTransitionService $transitions,
    ): int {
        // Unpaid purchase holds go back on the market.
        $holds = ServicePurchase::query()
            ->where('status', PurchaseStatus::PendingPayment->value)
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($holds as $purchase) {
            $purchases->expire($purchase);
        }

        // Active lesson plans past their validity: cancel future sessions and
        // refund the unused balance when the platform policy says so.
        $plans = ServicePurchase::query()
            ->where('status', PurchaseStatus::Active->value)
            ->where('service_type', ServiceType::Lesson->value)
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($plans as $purchase) {
            $this->expirePlan($purchase, $purchases, $refunds, $transitions);
        }

        $this->info("Expired {$holds->count()} purchase hold(s) and {$plans->count()} lesson plan(s).");

        return self::SUCCESS;
    }

    private function expirePlan(
        ServicePurchase $purchase,
        PurchaseService $purchases,
        RefundService $refunds,
        BookingTransitionService $transitions,
    ): void {
        // Scheduled sessions that can no longer run return their time first.
        $purchase->sessions()
            ->where('starts_at', '>', now())
            ->whereIn('status', [
                BookingStatus::Confirmed->value,
                BookingStatus::InProgress->value,
            ])
            ->get()
            ->each(fn (Booking $booking) => $transitions->cancel(
                $booking,
                Booking::CANCELLED_BY_ADMIN,
                __('The plan expired before this session could take place.'),
            ));

        $purchase->refresh();

        $remaining = $purchase->remainingMinutes();

        if ($remaining > 0
            && (bool) config('studylikepro.services.refund_unused_balance_on_expiry')
            && (int) $purchase->allocation_minutes > 0) {
            $payment = $purchases->capturedFor($purchase);

            if ($payment !== null) {
                $percent = (int) round($remaining * 100 / (int) $purchase->allocation_minutes);

                $refunds->refund(
                    $payment,
                    $percent,
                    Refund::INITIATED_BY_ADMIN,
                    __('Lesson plan expired with unused time.'),
                );
            }
        }

        $purchases->markExpired($purchase);
    }
}
