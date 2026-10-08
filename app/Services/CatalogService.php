<?php

namespace App\Services;

use App\Models\EducationLevel;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Subject;
use App\Models\SubjectBasket;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The public catalog (active levels with their grades and baskets, and active
 * subjects with their lessons grouped by grade) is read on nearly every page —
 * the directory filters, the interest pickers, the request form and the admin
 * matcher all need it. It changes when an admin edits it, so it is cached until
 * a curriculum write flushes it.
 *
 * The cache stores plain attribute rows and the models are rehydrated on read:
 * the seeded curriculum serializes to ~2 MB as hydrated models, more than a
 * database cache store can write on MySQL installations whose
 * `max_allowed_packet` is still the legacy 1 MB, while the same catalog as raw
 * rows is a few hundred kilobytes.
 */
class CatalogService
{
    public const CACHE_KEY = 'studylikepro:catalog:v6:levels';

    public const SUBJECT_CACHE_KEY = 'studylikepro:catalog:v6:subjects';

    /**
     * Active levels, ordered, each with its active grades and subject baskets.
     *
     * @return Collection<int, EducationLevel>
     */
    public function levels(): Collection
    {
        $payload = Cache::rememberForever(self::CACHE_KEY, fn () => $this->levelsPayload());

        return $this->hydrateLevels($payload);
    }

    /**
     * Active subjects, ordered, each with its level, basket and active lessons.
     *
     * @return Collection<int, Subject>
     */
    public function subjects(): Collection
    {
        $payload = Cache::rememberForever(self::SUBJECT_CACHE_KEY, fn () => $this->subjectsPayload());

        return $this->hydrateSubjects($payload);
    }

    /*
    |--------------------------------------------------------------------------
    | Cache payloads (attribute rows, not models)
    |--------------------------------------------------------------------------
    */

    /**
     * @return array{levels: list<array<string, mixed>>, grades: list<array<string, mixed>>, baskets: list<array<string, mixed>>}
     */
    private function levelsPayload(): array
    {
        $levels = EducationLevel::query()
            ->active()
            ->ordered()
            ->with([
                'grades' => fn ($query) => $query->active()->ordered(),
                'subjectBaskets' => fn ($query) => $query->active()->ordered(),
            ])
            ->get();

        return [
            'levels' => $levels->map->getAttributes()->values()->all(),
            'grades' => $levels->flatMap->grades->map->getAttributes()->values()->all(),
            'baskets' => $levels->flatMap->subjectBaskets->map->getAttributes()->values()->all(),
        ];
    }

    /**
     * @return array{subjects: list<array<string, mixed>>, lessons: list<array<string, mixed>>}
     */
    private function subjectsPayload(): array
    {
        $subjects = Subject::query()
            ->active()
            ->ordered()
            ->with(['lessons' => fn ($query) => $query->active()->ordered()])
            ->get();

        return [
            'subjects' => $subjects->map->getAttributes()->values()->all(),
            'lessons' => $subjects->flatMap->lessons->map->getAttributes()->values()->all(),
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Hydration
    |--------------------------------------------------------------------------
    */

    /**
     * @param  array{levels: list<array<string, mixed>>, grades: list<array<string, mixed>>, baskets: list<array<string, mixed>>}  $payload
     * @return Collection<int, EducationLevel>
     */
    private function hydrateLevels(array $payload): Collection
    {
        $grades = Grade::hydrate($payload['grades']);
        $baskets = SubjectBasket::hydrate($payload['baskets']);

        return collect($payload['levels'])->map(function (array $attributes) use ($grades, $baskets): EducationLevel {
            $level = (new EducationLevel)->newFromBuilder($attributes);

            $level->setRelation('grades', $grades->where('education_level_id', $level->id)->values());
            $level->setRelation('subjectBaskets', $baskets->where('education_level_id', $level->id)->values());

            return $level;
        });
    }

    /**
     * @param  array{subjects: list<array<string, mixed>>, lessons: list<array<string, mixed>>}  $payload
     * @return Collection<int, Subject>
     */
    private function hydrateSubjects(array $payload): Collection
    {
        $levels = $this->levels();
        $grades = $levels->flatMap->grades;
        $baskets = $levels->flatMap->subjectBaskets;

        $lessons = Lesson::hydrate($payload['lessons'])
            ->each(fn (Lesson $lesson) => $lesson->setRelation('grade', $grades->firstWhere('id', $lesson->grade_id)))
            ->groupBy('subject_id');

        return collect($payload['subjects'])->map(function (array $attributes) use ($levels, $baskets, $lessons): Subject {
            $subject = (new Subject)->newFromBuilder($attributes);

            $subject->setRelation('educationLevel', $levels->firstWhere('id', $subject->education_level_id));
            $subject->setRelation('basket', $baskets->firstWhere('id', $subject->basket_id));
            $subject->setRelation('lessons', $lessons->get($subject->id, new Collection));

            return $subject;
        });
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

    /**
     * Active subject baskets of one level (the O/L's three categories), taken
     * from the cached list when it is there.
     *
     * @return Collection<int, SubjectBasket>
     */
    public function basketsFor(EducationLevel $level): Collection
    {
        $cached = $this->levels()->firstWhere('id', $level->id);

        return $cached?->subjectBaskets ?? $level->subjectBaskets()->active()->ordered()->get();
    }

    /**
     * Subjects grouped by the basket they belong to, baskets in their own
     * order, prefixed with the group of compulsory subjects (the ones outside
     * every basket) - the shape the subject pickers render. Baskets that offer
     * nothing in the selection are dropped, and without any basket the result
     * is that single compulsory group, so callers can always iterate one list.
     *
     * @param  Collection<int, Subject>  $subjects
     * @param  Collection<int, SubjectBasket>  $baskets
     * @return Collection<int, array{basket: SubjectBasket|null, subjects: Collection<int, Subject>}>
     */
    public function groupByBasket(Collection $subjects, Collection $baskets): Collection
    {
        $basketIds = $baskets->pluck('id');

        $groups = $baskets->map(fn (SubjectBasket $basket) => [
            'basket' => $basket,
            'subjects' => $subjects->where('basket_id', $basket->id)->values(),
        ]);

        $groups->prepend([
            'basket' => null,
            'subjects' => $subjects
                ->reject(fn (Subject $subject) => $basketIds->contains($subject->basket_id))
                ->values(),
        ]);

        return $groups
            ->filter(fn (array $group) => $group['subjects']->isNotEmpty())
            ->values();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::SUBJECT_CACHE_KEY);
    }
}
