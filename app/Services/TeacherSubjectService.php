<?php

namespace App\Services;

use App\Enums\ApprovalStatus;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Subject;
use App\Models\TeacherProfile;
use Illuminate\Support\Collection;

/**
 * Shared subject-pivot rules for both onboarding (the verification step) and
 * the teacher's subject management page: new picks attach as pending with the
 * subject's full default scope, already-approved rows keep their approval, and
 * removed rows drop their lessons with them.
 */
class TeacherSubjectService
{
    /**
     * Replace the teacher's subject picks. Rows no longer picked are detached
     * (lessons included), new picks attach as pending, and rows the admin
     * already approved are left untouched.
     *
     * @param  array<int, mixed>  $selectedIds
     */
    public function syncSubjects(TeacherProfile $profile, array $selectedIds): void
    {
        $selected = collect($selectedIds)
            ->map(fn ($id) => (int) $id)
            ->filter(fn ($id) => $id > 0)
            ->unique()
            ->values();

        $pivotSubjects = $profile->subjects()->get();
        $current = $pivotSubjects->pluck('id');

        $removed = $current->diff($selected);

        if ($removed->isNotEmpty()) {
            $profile->lessons()->detach(
                Lesson::query()->whereIn('subject_id', $removed)->pluck('id')
            );

            $profile->subjects()->detach($removed->all());
        }

        $added = $selected->diff($current);

        if ($added->isNotEmpty()) {
            $profile->subjects()->attach(
                $added->mapWithKeys(fn (int $id) => [$id => ['status' => ApprovalStatus::Pending]])->all()
            );
        }

        foreach (Subject::query()->whereIn('id', $added)->get() as $subject) {
            $this->assignLevelDefaults($profile, $subject);
        }
    }

    /**
     * Grant one subject to the teacher: the pivot row becomes approved (created
     * if missing) and gets the subject's full default scope. Used when an admin
     * approves a subject request.
     */
    public function grantApproved(TeacherProfile $profile, Subject $subject): void
    {
        $profile->subjects()->syncWithoutDetaching([
            $subject->id => [
                'status' => ApprovalStatus::Approved,
                'approved_at' => now(),
            ],
        ]);

        $this->assignLevelDefaults($profile, $subject);
    }

    /**
     * Assign a subject's full default scope: every active grade of its level
     * and every active lesson in those grades.
     */
    public function assignLevelDefaults(TeacherProfile $profile, Subject $subject): void
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
