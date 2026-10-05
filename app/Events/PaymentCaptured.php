<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;

class PaymentCaptured
{
    use Dispatchable;

    public function __construct(public readonly Payment $payment) {}
}
