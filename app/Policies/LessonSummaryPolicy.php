<?php

namespace App\Policies;

use App\Enums\PurchaseStatus;
use App\Models\LessonSummary;
use App\Models\User;

class LessonSummaryPolicy
{
    /**
     * Admins, the student who bought the summary and the teacher who wrote it.
     */
    public function view(User $user, LessonSummary $summary): bool
    {
        return $user->isAdmin()
            || $summary->purchase?->student_id === $user->id
            || $summary->teacherProfile?->user_id === $user->id;
    }

    /**
     * Only the owning teacher may write, and only while the purchase is live.
     */
    public function write(User $user, LessonSummary $summary): bool
    {
        if ($summary->teacherProfile?->user_id !== $user->id) {
            return false;
        }

        return in_array(
            $summary->purchase?->status,
            [PurchaseStatus::Active, PurchaseStatus::Completed],
            true,
        );
    }

    /**
     * Delivery is the final act: the teacher's own draft on an active purchase.
     */
    public function deliver(User $user, LessonSummary $summary): bool
    {
        return $summary->teacherProfile?->user_id === $user->id
            && $summary->isDraft()
            && $summary->purchase?->isActive() === true;
    }
}
