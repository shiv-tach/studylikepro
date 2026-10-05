<?php

namespace App\Policies;

use App\Models\TutoringRequest;
use App\Models\User;

class TutoringRequestPolicy
{
    public function view(User $user, TutoringRequest $request): bool
    {
        if ($user->isAdmin() || $request->student_id === $user->id) {
            return true;
        }

        return $user->isTeacher()
            && $user->teacherProfile?->isApproved()
            && $request->topic_id !== null
            && $user->teacherProfile->topics()->where('topics.id', $request->topic_id)->exists();
    }

    public function updateTopic(User $user, TutoringRequest $request): bool
    {
        return $request->student_id === $user->id && $request->isOpen();
    }

    public function cancel(User $user, TutoringRequest $request): bool
    {
        return $request->student_id === $user->id && $request->isOpen();
    }

    public function respond(User $user, TutoringRequest $request): bool
    {
        return $user->isTeacher() && $request->isMatchable();
    }
}
