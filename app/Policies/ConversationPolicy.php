<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\User;

class ConversationPolicy
{
    /**
     * Only the two people in the lesson may read its chat.
     */
    public function view(User $user, Conversation $conversation): bool
    {
        return $conversation->isParticipant($user);
    }

    /**
     * Writing follows the same rule as reading.
     */
    public function post(User $user, Conversation $conversation): bool
    {
        return $conversation->isParticipant($user);
    }

    /**
     * Reporting a problem is for the two parties, not for admins.
     */
    public function report(User $user, Conversation $conversation): bool
    {
        return $conversation->isParticipant($user);
    }
}
