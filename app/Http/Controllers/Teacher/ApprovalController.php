<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\ApprovalStatus;
use App\Http\Controllers\Controller;
use App\Support\TeacherApproval;
use App\Support\TeacherOnboarding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The approval status page: what the teacher sees after submitting, and the
 * page the pending-approval workspace points at. It has three phases — in
 * review, live, and action needed — so submitting never reads as "done".
 */
class ApprovalController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        $profile = $user->teacherProfile;

        if (! $profile?->hasSubmittedVerification()) {
            return redirect()->route('teacher.verification');
        }

        $profile->loadMissing(['documents', 'subjects']);

        $stage = TeacherApproval::stage($profile);
        $expectedDecisionAt = TeacherApproval::expectedDecisionAt($profile);

        return view('teacher.approval', [
            'profile' => $profile,
            'stage' => $stage,
            'submittedAt' => display_datetime($profile->submitted_at),
            'expectedDecisionAt' => $expectedDecisionAt ? display_datetime($expectedDecisionAt) : null,
            'isOverdue' => TeacherApproval::isOverdue($profile),
            'steps' => TeacherOnboarding::steps($profile),
            'subjects' => $profile->subjects
                ->map(fn ($subject) => [
                    'name' => $subject->name,
                    'approved' => $subject->pivot->status === ApprovalStatus::Approved,
                ])
                ->values(),
            'documentCount' => $profile->documents->count(),
        ]);
    }
}
