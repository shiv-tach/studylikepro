<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An admin refused the uploaded slip: the proof could not be matched to a
 * deposit, so the student has to submit a clearer one (or another method).
 */
class ManualPaymentRejected
{
    use Dispatchable;

    public function __construct(
        public readonly Payment $payment,
        public readonly string $reason,
    ) {}
}
