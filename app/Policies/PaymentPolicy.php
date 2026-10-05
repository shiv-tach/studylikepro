<?php

namespace App\Policies;

use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function view(User $user, Payment $payment): bool
    {
        return $user->isAdmin() || $payment->student_id === $user->id;
    }

    public function refund(User $user, Payment $payment): bool
    {
        return $user->isAdmin();
    }
}
