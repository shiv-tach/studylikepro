<?php

namespace App\Policies;

use App\Models\ServiceOffering;
use App\Models\User;

class ServiceOfferingPolicy
{
    /**
     * Teachers manage their own catalog; admins moderate any of it.
     */
    public function manage(User $user, ServiceOffering $offering): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return $user->teacherProfile?->id === $offering->teacher_profile_id;
    }

    /**
     * Anyone can browse active offerings (catalog is public), so this gate
     * only matters for the management screens.
     */
    public function view(User $user, ServiceOffering $offering): bool
    {
        return true;
    }

    /**
     * A student may purchase an active offering published by an approved teacher.
     */
    public function buy(User $user, ServiceOffering $offering): bool
    {
        return $user->isStudent()
            && $offering->is_active
            && $offering->teacherProfile?->isApproved();
    }
}
