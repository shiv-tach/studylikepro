<?php

namespace App\Policies;

use App\Enums\MarkingOrderStatus;
use App\Models\PaperMarkingOrder;
use App\Models\User;

class PaperMarkingOrderPolicy
{
    /**
     * Admins, the owning student and the assigned teacher can look at an order.
     */
    public function view(User $user, PaperMarkingOrder $order): bool
    {
        if ($user->isAdmin() || $order->student_id === $user->id) {
            return true;
        }

        return $order->teacherProfile?->user_id === $user->id;
    }

    /**
     * Only the assigned teacher may take on or deliver the marking.
     */
    public function mark(User $user, PaperMarkingOrder $order): bool
    {
        return $order->teacherProfile?->user_id === $user->id
            && in_array($order->status, [MarkingOrderStatus::Submitted, MarkingOrderStatus::InProgress], true);
    }

    /**
     * Autosaving the studio follows the same rule as delivering it.
     */
    public function draft(User $user, PaperMarkingOrder $order): bool
    {
        return $this->mark($user, $order);
    }

    /**
     * The owning student accepts or disputes a returned paper.
     */
    public function accept(User $user, PaperMarkingOrder $order): bool
    {
        return $order->student_id === $user->id
            && $order->status === MarkingOrderStatus::Returned;
    }

    public function dispute(User $user, PaperMarkingOrder $order): bool
    {
        return $order->student_id === $user->id
            && $order->status === MarkingOrderStatus::Returned;
    }

    /**
     * Admins resolve disputed orders.
     */
    public function resolve(User $user, PaperMarkingOrder $order): bool
    {
        return $user->isAdmin() && $order->status === MarkingOrderStatus::Disputed;
    }

    /**
     * Downloading a paper follows the same access as viewing the order.
     */
    public function download(User $user, PaperMarkingOrder $order): bool
    {
        return $this->view($user, $order);
    }
}
