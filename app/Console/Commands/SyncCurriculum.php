<?php

namespace App\Console\Commands;

use App\Models\EducationLevel;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Subject;
use App\Services\CatalogService;
use App\Support\CurriculumSyllabus;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Renames the catalog's placeholder lessons ("Unit 01" …) to the official
 * syllabus units from database/data/curriculum.php.
 *
 * Rows are updated in place on purpose: teacher_lessons,
 * student_lesson_interests, bookings and service offerings all point at the
 * lesson ids, so renaming keeps every existing link. Lessons that no longer
 * fit the unit list are deactivated rather than deleted, and a lesson an admin
 * renamed by hand is left alone unless --force is passed.
 */
class SyncCurriculum extends Command
{
    protected $signature = 'studylikepro:sync-curriculum
                            {--dry-run : Report what would change without writing}
                            {--force : Also overwrite lesson titles an admin edited}
                            {--subject= : Limit to one subject (name or slug, e.g. Mathematics)}
                            {--grade= : Limit to one grade number (e.g. 7)}';

    protected $description = 'Rename placeholder lessons to the official syllabus units (English title + Sinhala subtitle)';

    /**
     * @var array{lists: int, created: int, renamed: int, deactivated: int, kept: int}
     */
    private array $totals = ['lists' => 0, 'created' => 0, 'renamed' => 0, 'deactivated' => 0, 'kept' => 0];

    public function handle(CatalogService $catalog): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $force = (bool) $this->option('force');
        $subjectFilter = $this->option('subject');
        $gradeFilter = $this->option('grade') === null ? null : (int) $this->option('grade');

        foreach (CurriculumSyllabus::data() as $levelKey => $subjects) {
            $level = EducationLevel::query()->where('key', $levelKey)->first();

            if ($level === null) {
                $this->warn("Level \"{$levelKey}\" is not in the database — skipped.");

                continue;
            }

            foreach (array_keys($subjects) as $subjectName) {
                $subject = $this->resolveSubject($level, $subjectName);

                if ($subject === null) {
                    $this->warn("{$level->name} · {$subjectName} is not in the database — skipped.");

                    continue;
                }

                if (is_string($subjectFilter) && $subjectFilter !== '' && ! $this->matchesFilter($subject, $subjectFilter)) {
                    continue;
                }

                $grades = $gradeFilter === null
                    ? CurriculumSyllabus::grades($levelKey, $subjectName)
                    : array_values(array_filter(
                        CurriculumSyllabus::grades($levelKey, $subjectName),
                        fn (int $number) => $number === $gradeFilter,
                    ));

                foreach ($grades as $gradeNumber) {
                    $grade = Grade::query()
                        ->where('education_level_id', $level->id)
                        ->where('number', $gradeNumber)
                        ->first();

                    if ($grade === null) {
                        $this->warn("{$subjectName}: {$gradeNumber} has no matching grade row — skipped.");

                        continue;
                    }

                    $this->syncList(
                        $subject,
                        $grade,
                        CurriculumSyllabus::lessons($levelKey, $subjectName, $gradeNumber),
                        $dryRun,
                        $force,
                    );
                }
            }
        }

        if (! $dryRun) {
            $catalog->flush();
        }

        $this->newLine();
        $this->info(sprintf(
            '%s %d subject/grade list(s): %d renamed, %d created, %d deactivated, %d kept%s.',
            $dryRun ? 'Would sync' : 'Synced',
            $this->totals['lists'],
            $this->totals['renamed'],
            $this->totals['created'],
            $this->totals['deactivated'],
            $this->totals['kept'],
            $dryRun ? ' (dry run — nothing written)' : '',
        ));

        return self::SUCCESS;
    }

    /**
     * One subject×grade list. Plans the whole list first so slug swaps never
     * collide with the (subject_id, grade_id, slug) unique index.
     *
     * @param  list<array{name: string, name_si: ?string, slug: string, sort_order: int}>  $units
     */
    private function syncList(Subject $subject, Grade $grade, array $units, bool $dryRun, bool $force): void
    {
        if ($units === []) {
            return;
        }

        $existing = $subject->lessons()
            ->where('grade_id', $grade->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->values();

        $titles = array_column($units, 'name');
        $subtitles = array_values(array_filter(array_column($units, 'name_si')));

        $this->totals['lists']++;

        /** @var list<array{lesson: Lesson|null, attributes: array<string, mixed>}> $plan */
        $plan = [];
        $kept = 0;

        foreach ($units as $position => $unit) {
            $lesson = $existing->get($position);

            if ($lesson === null) {
                $plan[] = ['lesson' => null, 'attributes' => $this->attributes($unit)];

                continue;
            }

            if (! $force && ! $this->isManaged($lesson, $titles, $subtitles)) {
                // An admin renamed this lesson to something outside the syllabus.
                $kept++;

                continue;
            }

            $plan[] = ['lesson' => $lesson, 'attributes' => $this->attributes($unit)];
        }

        $extras = $existing->slice(count($units))->values();

        // Applying a plan can move a slug between two rows, so park the slugs
        // of every row that changes first.
        if (! $dryRun) {
            foreach ($plan as $entry) {
                if ($entry['lesson'] !== null && $entry['lesson']->slug !== $entry['attributes']['slug']) {
                    $entry['lesson']->forceFill(['slug' => 'sync-'.$entry['lesson']->id])->saveQuietly();
                }
            }
        }

        $renamed = 0;
        $created = 0;

        foreach ($plan as $entry) {
            $lesson = $entry['lesson'];
            $attributes = $entry['attributes'];

            if ($lesson === null) {
                if (! $dryRun) {
                    $new = new Lesson($attributes);
                    $new->subject_id = $subject->id;
                    $new->grade_id = $grade->id;
                    $new->saveQuietly();
                }

                $created++;

                continue;
            }

            $dirty = collect($attributes)->filter(
                fn (mixed $value, string $key) => $lesson->{$key} != $value,
            );

            if ($dirty->isEmpty()) {
                continue;
            }

            if (! $dryRun) {
                foreach ($attributes as $key => $value) {
                    $lesson->{$key} = $value;
                }

                $lesson->saveQuietly();
            }

            $renamed++;
        }

        $deactivated = 0;

        foreach ($extras as $index => $extra) {
            if (! $force && ! $this->isManaged($extra, $titles, $subtitles)) {
                $kept++;

                continue;
            }

            if (! $extra->is_active && $extra->sort_order >= 1000) {
                continue;
            }

            if (! $dryRun) {
                $extra->is_active = false;
                $extra->sort_order = 1000 + $index;
                $extra->saveQuietly();
            }

            $deactivated++;
        }

        $this->totals['renamed'] += $renamed;
        $this->totals['created'] += $created;
        $this->totals['deactivated'] += $deactivated;
        $this->totals['kept'] += $kept;

        if ($renamed + $created + $deactivated + $kept === 0) {
            return;
        }

        $this->line(sprintf(
            '%s %s · %s — %d units: %d renamed, %d added, %d deactivated, %d kept',
            $dryRun ? 'Would sync' : 'Synced',
            $subject->name,
            $grade->label,
            count($units),
            $renamed,
            $created,
            $deactivated,
            $kept,
        ));
    }

    /**
     * @param  array{name: string, name_si: ?string, slug: string, sort_order: int}  $unit
     * @return array<string, mixed>
     */
    private function attributes(array $unit): array
    {
        return [
            'name' => $unit['name'],
            'name_si' => $unit['name_si'],
            'slug' => $unit['slug'],
            'sort_order' => $unit['sort_order'],
            'is_active' => true,
        ];
    }

    /**
     * A lesson this command owns: an untouched placeholder, or a row already
     * carrying one of the syllabus titles. Anything else was edited by an
     * admin and is never overwritten without --force.
     *
     * @param  list<string>  $titles
     * @param  list<string>  $subtitles
     */
    private function isManaged(Lesson $lesson, array $titles, array $subtitles): bool
    {
        if (preg_match('/^Unit \d+$/', (string) $lesson->name) === 1) {
            return true;
        }

        if (in_array($lesson->name, $titles, true)) {
            return true;
        }

        return $lesson->name_si !== null && in_array($lesson->name_si, $subtitles, true);
    }

    private function resolveSubject(EducationLevel $level, string $subjectName): ?Subject
    {
        return Subject::query()
            ->where('education_level_id', $level->id)
            ->where(function (Builder $query) use ($subjectName) {
                $query->where('name', $subjectName)
                    ->orWhere('slug', Str::slug($subjectName));
            })
            ->first();
    }

    private function matchesFilter(Subject $subject, string $filter): bool
    {
        return mb_strtolower($subject->name) === mb_strtolower($filter)
            || $subject->slug === Str::slug($filter);
    }
}
