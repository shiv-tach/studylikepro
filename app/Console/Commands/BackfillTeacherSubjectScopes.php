<?php

namespace App\Console\Commands;

use App\Enums\ApprovalStatus;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Services\TeacherSubjectService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;

/**
 * Older approvals only flipped the pivot status, so some approved subjects on
 * /teacher/subjects carry no grade scope and no lessons — which leaves the
 * /teacher/services grade -> subject -> lesson cascade empty. This repairs
 * those rows with the subject's level defaults (all active grades plus their
 * active lessons). Safe to re-run: rows that already carry a scope are skipped.
 */
class BackfillTeacherSubjectScopes extends Command
{
    protected $signature = 'studylikepro:backfill-subject-scopes
                            {--dry-run : Report the rows that would be repaired without writing}';

    protected $description = 'Give approved teacher subjects that never got a grade scope their level defaults';

    public function handle(TeacherSubjectService $teacherSubjects): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $profiles = TeacherProfile::query()
            ->with('user')
            ->whereHas('subjects', fn (Builder $query) => $this->unscopedApprovedSubjects($query))
            ->get();

        $repaired = 0;

        foreach ($profiles as $profile) {
            $rows = $profile->subjects()
                ->wherePivot('status', ApprovalStatus::Approved)
                ->get()
                ->filter(fn (Subject $subject) => array_map('intval', (array) ($subject->pivot->grade_levels ?? [])) === []);

            foreach ($rows as $subject) {
                if (! $dryRun) {
                    $teacherSubjects->assignLevelDefaults($profile, $subject);
                }

                $this->line(sprintf(
                    '%s #%d (%s) — %s',
                    $dryRun ? 'Would repair' : 'Repaired',
                    $profile->id,
                    $profile->user?->name ?? 'unknown teacher',
                    $subject->name
                ));

                $repaired++;
            }
        }

        $this->info($dryRun
            ? "{$repaired} approved subject row(s) have no grade scope."
            : "Repaired {$repaired} approved subject row(s).");

        return self::SUCCESS;
    }

    /**
     * Approved pivots whose grade scope is empty or missing.
     *
     * @param  Builder<Subject>  $query
     * @return Builder<Subject>
     */
    private function unscopedApprovedSubjects(Builder $query): Builder
    {
        return $query
            ->where('teacher_subjects.status', ApprovalStatus::Approved->value)
            ->where(function (Builder $inner) {
                $inner->whereNull('teacher_subjects.grade_levels')
                    ->orWhere('teacher_subjects.grade_levels', '')
                    ->orWhere('teacher_subjects.grade_levels', '[]');
            });
    }
}
