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
            && $request->lesson_id !== null
            && $user->teacherProfile->lessons()->where('lessons.id', $request->lesson_id)->exists();
    }

    public function updateLesson(User $user, TutoringRequest $request): bool
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
