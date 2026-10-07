<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubjectSetupRequest;
use App\Http\Requests\TeachingSetupRequest;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Subject;
use App\Models\TeacherProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubjectsController extends Controller
{
    /**
     * Manage the subjects and lessons this teacher teaches.
     */
    public function index(Request $request): View
    {
        $profile = $request->user()->teacherProfile;

        $subjects = Subject::query()
            ->active()
            ->ordered()
            ->with([
                'educationLevel.grades' => fn ($query) => $query->where('is_active', true)->ordered(),
                'lessons' => fn ($query) => $query->where('is_active', true)->ordered()->with('grade'),
            ])
            ->get();

        return view('teacher.subjects', [
            'subjects' => $subjects,
            'selectedSubjects' => $profile->subjects()->get()->keyBy('id'),
            'selectedLessons' => $profile->lessons()->pluck('lessons.id')->all(),
            'baseRateMinor' => (int) $profile->hourly_rate_minor,
        ]);
    }

    /**
     * Sync the teacher's subjects (detaching lessons of removed subjects).
     * A newly picked subject starts with every grade of its level and every
     * active lesson in those grades assigned; the teacher prunes from there.
     */
    public function store(TeachingSetupRequest $request): RedirectResponse
    {
        $profile = $request->user()->teacherProfile;
        $selected = $request->validated('subjects') ?? [];

        $current = $profile->subjects()->pluck('subjects.id');

        $removed = $current->diff($selected);

        if ($removed->isNotEmpty()) {
            $profile->lessons()->detach(
                Lesson::query()->whereIn('subject_id', $removed)->pluck('id')
            );
        }

        $profile->subjects()->sync($selected);

        $added = Subject::query()
            ->whereIn('id', collect($selected)->diff($current))
            ->get();

        foreach ($added as $subject) {
            $this->assignLevelDefaults($profile, $subject);
        }

        return redirect()
            ->route('teacher.subjects.index')
            ->with('status', 'subjects-updated');
    }

    /**
     * Update lessons, grade levels, and the default and per-grade rates for
     * one subject. The submitted grades are authoritative: a lesson that no
     * longer belongs to one of them is detached.
     */
    public function update(SubjectSetupRequest $request, Subject $subject): RedirectResponse
    {
        $profile = $request->user()->teacherProfile;

        abort_unless(
            $profile->subjects()->where('subjects.id', $subject->id)->exists(),
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

    /**
     * Assign a subject's full default scope: every active grade of its level
     * and every active lesson in those grades.
     */
    private function assignLevelDefaults(TeacherProfile $profile, Subject $subject): void
    {
        $gradeIds = Grade::query()
            ->where('education_level_id', $subject->education_level_id)
            ->where('is_active', true)
            ->ordered()
            ->pluck('id');

        $lessonIds = Lesson::query()
            ->where('subject_id', $subject->id)
            ->where('is_active', true)
            ->whereIn('grade_id', $gradeIds)
            ->pluck('id');

        $profile->lessons()->syncWithoutDetaching($lessonIds->all());

        $profile->subjects()->updateExistingPivot($subject->id, [
            'grade_levels' => $gradeIds->map(fn ($id) => (string) $id)->all(),
        ]);
    }
}
