<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\ApprovalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubjectSetupRequest;
use App\Http\Requests\TeacherSubjectRequest;
use App\Models\EducationLevel;
use App\Models\Lesson;
use App\Models\Subject;
use App\Models\SubjectRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubjectsController extends Controller
{
    /**
     * Manage the subjects this teacher teaches: approved subjects carry their
     * lesson/grade/rate setup here, while pending and rejected rows and open
     * subject requests are listed with their status.
     */
    public function index(Request $request): View
    {
        $profile = $request->user()->teacherProfile;

        $pivotSubjects = $profile->subjects()
            ->with([
                'educationLevel.grades' => fn ($query) => $query->where('is_active', true)->ordered(),
                'lessons' => fn ($query) => $query->where('is_active', true)->ordered()->with('grade'),
            ])
            ->get();

        $ownedIds = $pivotSubjects->pluck('id')->all();
        $openRequestIds = $profile->subjectRequests()
            ->pending()
            ->whereNotNull('subject_id')
            ->pluck('subject_id')
            ->all();

        $availableSubjects = Subject::query()
            ->active()
            ->ordered()
            ->with('educationLevel')
            ->whereNotIn('id', array_merge($ownedIds, $openRequestIds))
            ->get();

        return view('teacher.subjects', [
            'subjects' => $pivotSubjects->filter(fn (Subject $subject) => $subject->pivot->status === ApprovalStatus::Approved)->values(),
            'pendingSubjects' => $pivotSubjects->filter(fn (Subject $subject) => $subject->pivot->status === ApprovalStatus::Pending)->values(),
            'rejectedSubjects' => $pivotSubjects->filter(fn (Subject $subject) => $subject->pivot->status === ApprovalStatus::Rejected)->values(),
            'availableSubjects' => $availableSubjects,
            'levels' => EducationLevel::query()->active()->ordered()->get(),
            'subjectRequests' => $profile->subjectRequests()
                ->latest()
                ->limit(15)
                ->with(['subject', 'educationLevel'])
                ->get(),
            'selectedLessons' => $profile->lessons()->pluck('lessons.id')->all(),
            'baseRateMinor' => (int) $profile->hourly_rate_minor,
        ]);
    }

    /**
     * Ask to teach catalog subjects the teacher does not already have. Each
     * pick becomes a pending subject request for the admin to approve.
     */
    public function addSubjects(TeacherSubjectRequest $request): RedirectResponse
    {
        $profile = $request->user()->teacherProfile;

        $blocked = array_map('intval', array_merge(
            $profile->subjects()->pluck('subjects.id')->all(),
            $profile->subjectRequests()->pending()->whereNotNull('subject_id')->pluck('subject_id')->all()
        ));

        $added = 0;

        foreach (array_unique(array_map('intval', $request->validated('subject_ids') ?? [])) as $subjectId) {
            if (in_array($subjectId, $blocked, true)) {
                continue;
            }

            $profile->subjectRequests()->create([
                'subject_id' => $subjectId,
                'status' => ApprovalStatus::Pending,
            ]);

            $added++;
        }

        return redirect()
            ->route('teacher.subjects.index')
            ->with('status', $added > 0 ? 'subjects-requested' : 'no-new-subjects');
    }

    /**
     * Propose a subject that is not in the catalog yet. Admin approval creates
     * the subject and grants it to the teacher.
     */
    public function storeRequest(TeacherSubjectRequest $request): RedirectResponse
    {
        $profile = $request->user()->teacherProfile;

        $name = trim((string) $request->validated('proposed_name'));

        if (Subject::query()->where('is_active', true)->whereRaw('lower(name) = ?', [mb_strtolower($name)])->exists()) {
            return back()->withErrors([
                'proposed_name' => 'This subject already exists in the catalog — add it from the list above.',
            ])->withInput();
        }

        if ($profile->subjectRequests()->pending()->whereRaw('lower(proposed_name) = ?', [mb_strtolower($name)])->exists()) {
            return back()->withErrors([
                'proposed_name' => 'You already have an open request for this subject.',
            ])->withInput();
        }

        $profile->subjectRequests()->create([
            'proposed_name' => $name,
            'education_level_id' => (int) $request->validated('education_level_id'),
            'note' => $request->validated('note'),
            'status' => ApprovalStatus::Pending,
        ]);

        return redirect()
            ->route('teacher.subjects.index')
            ->with('status', 'subject-request-created');
    }

    /**
     * Withdraw one of the teacher's own pending requests.
     */
    public function cancelRequest(Request $request, SubjectRequest $subjectRequest): RedirectResponse
    {
        $profile = $request->user()->teacherProfile;

        abort_unless(
            (int) $subjectRequest->teacher_profile_id === (int) $profile->id
                && $subjectRequest->status === ApprovalStatus::Pending,
            403
        );

        $subjectRequest->delete();

        return redirect()
            ->route('teacher.subjects.index')
            ->with('status', 'subject-request-cancelled');
    }

    /**
     * Update lessons, grade levels, and the default and per-grade rates for
     * one approved subject. The submitted grades are authoritative: a lesson
     * that no longer belongs to one of them is detached.
     */
    public function update(SubjectSetupRequest $request, Subject $subject): RedirectResponse
    {
        $profile = $request->user()->teacherProfile;

        abort_unless(
            $profile->subjects()
                ->where('subjects.id', $subject->id)
                ->wherePivot('status', ApprovalStatus::Approved)
                ->exists(),
            403
        );

        $gradeIds = $request->scopedGradeIds();

        $lessons = collect($request->validated('lessons') ?? []);

        if ($gradeIds !== null) {
            $lessons = Lesson::query()
                ->whereIn('id', $lessons)
                ->whereIn('grade_id', $gradeIds)
                ->pluck('id');
        }

        $otherLessons = $profile->lessons()
            ->where('lessons.subject_id', '!=', $subject->id)
            ->pluck('lessons.id');

        $profile->lessons()->sync($otherLessons->merge($lessons)->all());

        $rate = $request->validated('rate_per_hour');

        $pivot = [
            'rate_per_hour_minor' => $rate !== null ? (int) round(((float) $rate) * 100) : null,
        ];

        if ($gradeIds !== null) {
            // Grade ids are stored as strings so the directory filter can
            // match them inside the pivot's JSON array.
            $pivot['grade_levels'] = array_map('strval', $gradeIds);

            $gradeRates = $request->scopedGradeRates();

            if ($gradeRates !== null) {
                $pivot['grade_rates'] = $gradeRates === [] ? null : $gradeRates;
            }
        }

        $profile->subjects()->updateExistingPivot($subject->id, $pivot);

        return redirect()
            ->route('teacher.subjects.index')
            ->with('status', 'subject-setup-updated');
    }
}
