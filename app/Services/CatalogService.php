<?php

namespace App\Services;

use App\Models\EducationLevel;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Subject;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The public catalog (active levels with their grades, and active subjects with
 * their lessons grouped by grade) is read on nearly every page — the directory
 * filters, the interest pickers, the request form and the admin matcher all
 * need it. It changes when an admin edits it, so it is cached until a
 * curriculum write flushes it.
 */
class CatalogService
{
    public const CACHE_KEY = 'studylikepro:catalog:v3:levels';

    public const SUBJECT_CACHE_KEY = 'studylikepro:catalog:v3:subjects';

    /**
     * Active levels, ordered, each with its active grades.
     *
     * @return Collection<int, EducationLevel>
     */
    public function levels(): Collection
    {
        return Cache::rememberForever(self::CACHE_KEY, fn () => EducationLevel::query()
            ->active()
            ->ordered()
            ->with(['grades' => fn ($query) => $query->active()->ordered()])
            ->get());
    }

    /**
     * Active subjects, ordered, each with its level and its active lessons.
     *
     * @return Collection<int, Subject>
     */
    public function subjects(): Collection
    {
        return Cache::rememberForever(self::SUBJECT_CACHE_KEY, fn () => Subject::query()
            ->active()
            ->ordered()
            ->with([
                'educationLevel',
                'lessons' => fn ($query) => $query->active()->ordered()->with('grade'),
            ])
            ->get());
    }

    /**
     * Active subjects that run in one grade: those of the grade's level with at
     * least one active lesson in the grade, each carrying only the grade's
     * lessons so callers can show a grade-scoped count.
     *
     * @return Collection<int, Subject>
     */
    public function subjectsForGrade(Grade $grade): Collection
    {
        return $this->subjects()
            ->where('education_level_id', $grade->education_level_id)
            ->map(function (Subject $subject) use ($grade): Subject {
                $scoped = clone $subject;
                $scoped->setRelation('lessons', $subject->lessons->where('grade_id', $grade->id)->values());

                return $scoped;
            })
            ->filter(fn (Subject $subject) => $subject->lessons->isNotEmpty())
            ->values();
    }

    /**
     * Active lessons of one subject, taken from the cached list when it is there.
     *
     * @return Collection<int, Lesson>
     */
    public function lessonsFor(Subject $subject): Collection
    {
        $cached = $this->subjects()->firstWhere('id', $subject->id);

        return $cached?->lessons ?? $subject->lessons()->active()->ordered()->with('grade')->get();
    }

    /**
     * Active grades of one level, taken from the cached list when it is there.
     *
     * @return Collection<int, Grade>
     */
    public function gradesFor(EducationLevel $level): Collection
    {
        $cached = $this->levels()->firstWhere('id', $level->id);

        return $cached?->grades ?? $level->grades()->active()->ordered()->get();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::SUBJECT_CACHE_KEY);
    }
}
