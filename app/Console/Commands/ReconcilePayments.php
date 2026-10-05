<?php

namespace App\Console\Commands;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Console\Command;

/**
 * Safety net for payments the gateway never confirmed: ask the provider what
 * happened to orders that are still open, and apply the answer.
 */
class ReconcilePayments extends Command
{
    protected $signature = 'payments:reconcile {--minutes= : Only look at payments older than this}';

    protected $description = 'Sync stuck payment orders with the gateway';

    public function handle(PaymentService $payments): int
    {
        $minutes = (int) ($this->option('minutes') ?? config('studylikepro.payments.reconcile_after_minutes'));

        $stuck = Payment::query()
            ->stuck($minutes)
            ->with('booking')
            ->get();

        $checked = 0;
        $confirmed = 0;

        foreach ($stuck as $payment) {
            if ($payment->gateway_payment_id === null) {
                // No payment attempt was ever started for this order.
                continue;
            }

            $checked++;

            $payments->syncFromGateway($payment);

            if ($payment->refresh()->status === PaymentStatus::Captured) {
                $confirmed++;
            }
        }

        $this->info("Checked {$checked} open payment(s) with the gateway; confirmed {$confirmed}.");

        return self::SUCCESS;
    }
}
