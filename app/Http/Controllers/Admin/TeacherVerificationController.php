<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApprovalStatus;
use App\Enums\VerificationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RejectVerificationRequest;
use App\Models\TeacherProfile;
use App\Notifications\TeacherVerificationApproved;
use App\Notifications\TeacherVerificationRejected;
use App\Services\TeacherSubjectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeacherVerificationController extends Controller
{
    public function __construct(private readonly TeacherSubjectService $teacherSubjects) {}

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
            // Applications that have waited past the promise made to teachers.
            'overdue' => TeacherProfile::query()->overdueForReview()->count(),
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
     *
     * Approval runs through TeacherSubjectService so the granted subject keeps
     * its full default scope: every active grade of its level plus the lessons
     * in those grades, which is what the teacher's /teacher/subjects setup and
     * the /teacher/services grade -> subject -> lesson cascade read.
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
            if ($approvedIds->contains($subject->id)) {
                $this->teacherSubjects->grantApproved($teacherProfile, $subject);
            } else {
                $this->teacherSubjects->reject($teacherProfile, $subject);
            }
        }

        $teacherProfile->update([
            'verification_status' => VerificationStatus::Approved,
            'verified_at' => now(),
            'verification_notes' => null,
            // A one-time "You're live" screen is owed from now on — including
            // when a previously approved teacher is approved again.
            'approved_seen_at' => null,
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
     * Confirm (or remove) the government-teacher badge.
     *
     * The survey lets a teacher claim a government post and attach proof; the
     * public badge only ever renders from this confirmation, never from the
     * claim itself. It can be toggled after approval as well, because a proof
     * often arrives later.
     */
    public function government(Request $request, TeacherProfile $teacherProfile): RedirectResponse
    {
        $validated = $request->validate([
            'government_verified' => ['required', 'boolean'],
        ]);

        $confirmed = (bool) $validated['government_verified'];

        $teacherProfile->update([
            'government_verified_at' => $confirmed
                ? ($teacherProfile->government_verified_at ?? now())
                : null,
        ]);

        return redirect()
            ->route('admin.verifications.show', $teacherProfile)
            ->with('status', $confirmed ? 'government-teacher-confirmed' : 'government-teacher-cleared');
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
