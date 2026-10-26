<?php

namespace App\Policies;

use App\Enums\PaymentStatus;
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

    /**
     * The payer may pick a manual method while the checkout is still open.
     */
    public function selectMethod(User $user, Payment $payment): bool
    {
        return $payment->student_id === $user->id
            && in_array($payment->status, [
                PaymentStatus::Created,
                PaymentStatus::Submitted,
                PaymentStatus::Rejected,
            ], true);
    }

    /**
     * The payer may upload (or replace) a slip until the payment is settled.
     */
    public function uploadSlip(User $user, Payment $payment): bool
    {
        return $this->selectMethod($user, $payment);
    }

    public function viewSlip(User $user, Payment $payment): bool
    {
        return $user->isAdmin() || $payment->student_id === $user->id;
    }

    public function verify(User $user, Payment $payment): bool
    {
        return $user->isAdmin();
    }

    public function reject(User $user, Payment $payment): bool
    {
        return $user->isAdmin();
    }
}
