<?php

namespace App\Events;

use App\Models\Payout;
use Illuminate\Foundation\Events\Dispatchable;

class PayoutPaid
{
    use Dispatchable;

    public function __construct(public readonly Payout $payout) {}
}
