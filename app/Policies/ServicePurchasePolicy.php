<?php

namespace App\Policies;

use App\Enums\PurchaseStatus;
use App\Models\ServicePurchase;
use App\Models\User;

class ServicePurchasePolicy
{
    public function view(User $user, ServicePurchase $purchase): bool
    {
        return $user->isAdmin()
            || $purchase->student_id === $user->id
            || $purchase->teacherProfile?->user_id === $user->id;
    }

    /**
     * The owning student pays for their own pending purchase.
     */
    public function pay(User $user, ServicePurchase $purchase): bool
    {
        return $purchase->student_id === $user->id
            && $purchase->status === PurchaseStatus::PendingPayment;
    }

    /**
     * Only the plan owner books sessions against their plan.
     */
    public function scheduleSession(User $user, ServicePurchase $purchase): bool
    {
        return $purchase->student_id === $user->id
            && $purchase->isLessonPlan()
            && $purchase->status === PurchaseStatus::Active;
    }

    public function cancel(User $user, ServicePurchase $purchase): bool
    {
        if ($user->isAdmin()) {
            return ! $purchase->status->isFinal();
        }

        if ($purchase->student_id === $user->id) {
            return $purchase->status === PurchaseStatus::PendingPayment;
        }

        return false;
    }
}
