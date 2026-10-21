<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ApprovalStatus;
use App\Enums\GradeBand;
use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\SubjectRequest;
use App\Notifications\SubjectRequestApproved;
use App\Notifications\SubjectRequestRejected;
use App\Services\TeacherSubjectService;
use App\Support\SubjectSlugs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubjectRequestController extends Controller
{
    public function __construct(private readonly TeacherSubjectService $teacherSubjects) {}

    /**
     * The subject request queue with status tabs.
     */
    public function index(Request $request): View
    {
        $status = $request->query('status', 'pending');
        $allowed = ['pending', 'approved', 'rejected', 'all'];

        if (! in_array($status, $allowed, true)) {
            $status = 'pending';
        }

        $requests = SubjectRequest::query()
            ->with(['teacherProfile.user', 'subject', 'educationLevel'])
            ->when($status !== 'all', fn ($query) => $query->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $counts = [
            'pending' => SubjectRequest::query()->where('status', ApprovalStatus::Pending)->count(),
            'approved' => SubjectRequest::query()->where('status', ApprovalStatus::Approved)->count(),
            'rejected' => SubjectRequest::query()->where('status', ApprovalStatus::Rejected)->count(),
        ];

        return view('admin.subject-requests.index', [
            'requests' => $requests,
            'status' => $status,
            'counts' => $counts,
        ]);
    }

    /**
     * Approve a request. A proposed new subject is created in the catalog
     * (default grade band, active, no basket) and granted to the teacher with
     * its full default scope; an existing-subject request only grants it.
     */
    public function approve(SubjectRequest $subjectRequest): RedirectResponse
    {
        if ($subjectRequest->status !== ApprovalStatus::Pending) {
            return back()->with('status', 'request-not-pending');
        }

        $teacher = $subjectRequest->teacherProfile;

        if ($subjectRequest->isNewSubject()) {
            abort_unless($subjectRequest->educationLevel !== null, 422, 'The request has no level.');

            $subject = Subject::create([
                'education_level_id' => $subjectRequest->education_level_id,
                'grade_band' => GradeBand::All,
                'name' => $subjectRequest->proposed_name,
                'slug' => SubjectSlugs::unique($subjectRequest->proposed_name, (int) $subjectRequest->education_level_id),
                'icon' => '📘',
                'is_active' => true,
                'sort_order' => 0,
            ]);
        } else {
            $subject = $subjectRequest->subject;

            abort_unless($subject !== null, 422, 'The requested subject no longer exists.');
        }

        $this->teacherSubjects->grantApproved($teacher, $subject);

        $subjectRequest->update([
            'status' => ApprovalStatus::Approved,
            'resolved_at' => now(),
        ]);

        $teacher->user->notify(new SubjectRequestApproved($subject->name));

        return redirect()
            ->route('admin.subject-requests.index')
            ->with('status', 'request-approved');
    }

    /**
     * Reject a request with a note the teacher can read.
     */
    public function reject(Request $request, SubjectRequest $subjectRequest): RedirectResponse
    {
        if ($subjectRequest->status !== ApprovalStatus::Pending) {
            return back()->with('status', 'request-not-pending');
        }

        $validated = $request->validate([
            'admin_note' => ['required', 'string', 'min:3', 'max:1000'],
        ]);

        $subjectRequest->update([
            'status' => ApprovalStatus::Rejected,
            'admin_note' => $validated['admin_note'],
            'resolved_at' => now(),
        ]);

        $subjectRequest->teacherProfile->user->notify(new SubjectRequestRejected(
            $subjectRequest->subject?->name ?? $subjectRequest->proposed_name,
            $validated['admin_note']
        ));

        return redirect()
            ->route('admin.subject-requests.index')
            ->with('status', 'request-rejected');
    }
}
