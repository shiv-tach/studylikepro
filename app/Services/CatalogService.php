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
    public const CACHE_KEY = 'studylikepro:catalog:v7:levels';

    public const SUBJECT_CACHE_KEY = 'studylikepro:catalog:v7:subjects';

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
     * Active subjects of active levels, ordered, each with its level, basket and
     * active lessons. Subjects whose level was deactivated (or is missing) drop
     * out with it: the pickers cascade level → subject → lesson, so a subject
     * without a level has nowhere to sit.
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
        })->filter(fn (Subject $subject) => $subject->educationLevel !== null)->values();
    }

    /**
     * Active subjects that run in one grade: those of the grade's level inside
     * their grade band with at least one active lesson in the grade, each
     * carrying only the grade's lessons so callers can show a grade-scoped
     * count.
     *
     * @return Collection<int, Subject>
     */
    public function subjectsForGrade(Grade $grade): Collection
    {
        return $this->gradeSubjects($this->subjects(), $grade);
    }

    /**
     * How many subjects run in each grade of a level, keyed by grade id. The
     * catalog is hydrated once, so a level's grade cards cost a single read.
     *
     * @return array<int, int>
     */
    public function gradeSubjectCounts(EducationLevel $level): array
    {
        $subjects = $this->subjects();

        return $level->grades
            ->mapWithKeys(fn (Grade $grade) => [$grade->id => $this->gradeSubjects($subjects, $grade)->count()])
            ->all();
    }

    /**
     * The subjects of one grade taken from an already-hydrated catalog - the
     * shared half of subjectsForGrade() and the basket groups, so a caller
     * that needs several grades hydrates the catalog once.
     *
     * @param  Collection<int, Subject>  $subjects
     * @return Collection<int, Subject>
     */
    private function gradeSubjects(Collection $subjects, Grade $grade): Collection
    {
        return $subjects
            ->where('education_level_id', $grade->education_level_id)
            ->filter(fn (Subject $subject) => $subject->appliesToGrade($grade))
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
     * The active baskets a grade chooses from, in basket order: Category II for
     * the junior grades and all three O/L baskets for Grades 10-11. Empty for
     * a grade that picks nothing.
     *
     * @return Collection<int, SubjectBasket>
     */
    public function basketsForGrade(Grade $grade): Collection
    {
        $level = $this->levelFor($grade);

        if ($level === null || ! $level->requiresBasketSelection($grade)) {
            return collect();
        }

        return $this->basketsFor($level)
            ->filter(fn (SubjectBasket $basket) => $basket->appliesToGrade($grade))
            ->values();
    }

    /**
     * The education level of a grade, taken from the cached catalog so a grade
     * that was never loaded with its level costs no extra query.
     */
    private function levelFor(Grade $grade): ?EducationLevel
    {
        if ($grade->relationLoaded('educationLevel')) {
            return $grade->educationLevel;
        }

        return $this->levels()->firstWhere('id', $grade->education_level_id);
    }

    /**
     * The subject baskets a grade chooses from - each carrying only the
     * subjects that run in that grade and belong to it - in basket order.
     * Empty for every grade without a basket choice.
     *
     * @return Collection<int, array{basket: SubjectBasket, subjects: Collection<int, Subject>}>
     */
    public function basketGroupsForGrade(Grade $grade): Collection
    {
        $level = $this->levelFor($grade);

        if ($level === null || ! $level->requiresBasketSelection($grade)) {
            return collect();
        }

        return $this->basketGroupsFor($grade, $this->basketsFor($level), $this->subjectsForGrade($grade));
    }

    /**
     * The subject baskets of every grade that chooses from baskets, keyed by
     * grade id - built from one pass over the catalog, which is what the
     * onboarding wizard needs for all of its grade steps at once.
     *
     * @param  Collection<int, EducationLevel>  $levels  the levels from levels(), each carrying its grades and baskets
     * @return array<int, Collection<int, array{basket: SubjectBasket, subjects: Collection<int, Subject>}>>
     */
    public function basketGroupsByGrade(Collection $levels): array
    {
        $subjects = $this->subjects();
        $groups = [];

        foreach ($levels as $level) {
            foreach ($level->grades as $grade) {
                if (! $level->requiresBasketSelection($grade)) {
                    continue;
                }

                $gradeGroups = $this->basketGroupsFor(
                    $grade,
                    $level->subjectBaskets,
                    $this->gradeSubjects($subjects, $grade),
                );

                if ($gradeGroups->isNotEmpty()) {
                    $groups[$grade->id] = $gradeGroups;
                }
            }
        }

        return $groups;
    }

    /**
     * @param  Collection<int, SubjectBasket>  $baskets
     * @param  Collection<int, Subject>  $subjects
     * @return Collection<int, array{basket: SubjectBasket, subjects: Collection<int, Subject>}>
     */
    private function basketGroupsFor(Grade $grade, Collection $baskets, Collection $subjects): Collection
    {
        return $baskets
            ->filter(fn (SubjectBasket $basket) => $basket->appliesToGrade($grade))
            ->map(fn (SubjectBasket $basket) => [
                'basket' => $basket,
                'subjects' => $subjects->where('basket_id', $basket->id)->values(),
            ])
            ->filter(fn (array $group) => $group['subjects']->isNotEmpty())
            ->values();
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
