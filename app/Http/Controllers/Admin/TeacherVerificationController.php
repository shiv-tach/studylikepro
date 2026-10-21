<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApprovalStatus;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RejectVerificationRequest;
use App\Models\TeacherProfile;
use App\Notifications\TeacherVerificationApproved;
use App\Notifications\TeacherVerificationRejected;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeacherVerificationController extends Controller
{
    /**
     * The verification queue with status tabs.
     */
    public function index(Request $request): View
    {
        $status = $request->query('status', 'pending');
        $allowed = ['pending', 'approved', 'rejected', 'all'];

        if (! in_array($status, $allowed, true)) {
            $status = 'pending';
        }

        $profiles = TeacherProfile::query()
            ->with('user')
            ->withCount('documents')
            ->when($status !== 'all', fn ($query) => $query->where('verification_status', $status))
            ->orderByDesc('submitted_at')
            ->orderByDesc('updated_at')
            ->paginate(15)
            ->withQueryString();

        $counts = [
            'pending' => TeacherProfile::query()->where('verification_status', VerificationStatus::Pending)->count(),
            'approved' => TeacherProfile::query()->where('verification_status', VerificationStatus::Approved)->count(),
            'rejected' => TeacherProfile::query()->where('verification_status', VerificationStatus::Rejected)->count(),
        ];

        return view('admin.verifications.index', [
            'profiles' => $profiles,
            'status' => $status,
            'counts' => $counts,
        ]);
    }

    /**
     * Review a single application.
     */
    public function show(TeacherProfile $teacherProfile): View
    {
        return view('admin.verifications.show', [
            'profile' => $teacherProfile->load(['user', 'documents']),
        ]);
    }

    /**
     * Approve a pending application. Every pending subject is approved unless
     * the review form explicitly unchecked it (then it is marked rejected); a
     * legacy approval that posts no subject list approves them all.
     */
    public function approve(Request $request, TeacherProfile $teacherProfile): RedirectResponse
    {
        if ($teacherProfile->verification_status !== VerificationStatus::Pending) {
            return back()->with('status', 'verification-not-pending');
        }

        $pendingSubjects = $teacherProfile->subjects()
            ->wherePivot('status', ApprovalStatus::Pending)
            ->get();

        $approvedIds = collect($request->input('subject_ids', $pendingSubjects->pluck('id')->all()))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $pendingSubjects->contains('id', $id))
            ->values();

        foreach ($pendingSubjects as $subject) {
            $teacherProfile->subjects()->updateExistingPivot(
                $subject->id,
                $approvedIds->contains($subject->id)
                    ? ['status' => ApprovalStatus::Approved, 'approved_at' => now()]
                    : ['status' => ApprovalStatus::Rejected]
            );
        }

        $teacherProfile->update([
            'verification_status' => VerificationStatus::Approved,
            'verified_at' => now(),
            'verification_notes' => null,
        ]);

        $teacherProfile->user->notify(new TeacherVerificationApproved(
            $teacherProfile->subjects()
                ->wherePivot('status', ApprovalStatus::Approved)
                ->pluck('subjects.name')
                ->all()
        ));

        return redirect()
            ->route('admin.verifications.index')
            ->with('status', 'teacher-approved');
    }

    /**
     * Reject a pending application with a reason.
     */
    public function reject(RejectVerificationRequest $request, TeacherProfile $teacherProfile): RedirectResponse
    {
        if ($teacherProfile->verification_status !== VerificationStatus::Pending) {
            return back()->with('status', 'verification-not-pending');
        }

        $reason = $request->validated('reason');

        $teacherProfile->update([
            'verification_status' => VerificationStatus::Rejected,
            'verified_at' => null,
            'verification_notes' => $reason,
        ]);

        $teacherProfile->user->notify(new TeacherVerificationRejected($reason));

        return redirect()
            ->route('admin.verifications.index')
            ->with('status', 'teacher-rejected');
    }
}
