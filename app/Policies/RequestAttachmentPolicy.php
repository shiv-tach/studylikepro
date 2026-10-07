<?php

namespace App\Policies;

use App\Models\RequestAttachment;
use App\Models\User;

class RequestAttachmentPolicy
{
    public function view(User $user, RequestAttachment $attachment): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        $request = $attachment->tutoringRequest;

        if ($request->student_id === $user->id) {
            return true;
        }

        return $user->isTeacher()
            && $user->teacherProfile?->isApproved()
            && $request->lesson_id !== null
            && $user->teacherProfile->lessons()->where('lessons.id', $request->lesson_id)->exists();
    }
}
