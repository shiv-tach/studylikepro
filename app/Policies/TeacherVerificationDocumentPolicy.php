<?php

namespace App\Policies;

use App\Models\TeacherVerificationDocument;
use App\Models\User;

class TeacherVerificationDocumentPolicy
{
    public function view(User $user, TeacherVerificationDocument $document): bool
    {
        return $user->isAdmin() || $document->teacherProfile->user_id === $user->id;
    }

    public function delete(User $user, TeacherVerificationDocument $document): bool
    {
        return $user->isTeacher()
            && $document->teacherProfile->user_id === $user->id
            && $document->teacherProfile->verification_status->allowsDocumentEditing();
    }
}
