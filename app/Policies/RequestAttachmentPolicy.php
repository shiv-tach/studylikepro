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
            && $request->topic_id !== null
            && $user->teacherProfile->topics()->where('topics.id', $request->topic_id)->exists();
    }
}
