<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\StudentOnboardingRequest;
use App\Models\EducationLevel;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\SubjectBasket;
use App\Services\CatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The wizard every new student finishes before the workspace opens: pick the
 * education level, the grade, the O/L basket subjects that grade chooses (if
 * any) and the learning language, then confirm the summary.
 *
 * The whole wizard is one page and one submit - the steps are client-side, so
 * a mistake is caught server-side and the student is put back on the step that
 * owns the error.
 */
class OnboardingController extends Controller
{
    public function __construct(private readonly CatalogService $catalog) {}

    /**
     * Show the wizard for a student who has not finished setting up yet.
     */
    public function show(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if ($user->hasCompletedOnboarding()) {
            return redirect()->route('student.dashboard');
        }

        $profile = $user->studentProfile;
        $levels = $this->catalog->levels();
        $basketGroups = $this->basketGroups($levels);
        $languages = config('studylikepro.student_learning_languages');

        $gradeId = (int) old('grade_id', $profile?->grade_id);

        return view('student.onboarding', [
            'levels' => $levels,
            'basketGroups' => $basketGroups,
            'languages' => $languages,
            'wizard' => [
                'levels' => $this->levelPayload($levels, $basketGroups),
                'basketNames' => $this->basketNames($basketGroups),
                'subjectNames' => $this->subjectNames($basketGroups),
                'languages' => $languages,
                'initial' => [
                    'step' => $this->initialStep($basketGroups, $gradeId),
                    'level' => (int) old('level_id', $profile?->grade?->education_level_id) ?: null,
                    'grade' => $gradeId ?: null,
                    'subjects' => $this->submittedBaskets(),
                    'language' => old('learning_language', $profile?->learning_language),
                ],
            ],
        ]);
    }

    /**
     * Save the finished wizard: the profile is complete, which unlocks the
     * student workspace, and the basket picks become the student's interests.
     */
    public function store(StudentOnboardingRequest $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->hasCompletedOnboarding()) {
            return redirect()->route('student.dashboard');
        }

        $profile = $user->studentProfile()->firstOrNew([]);
        $profile->fill([
            'grade_id' => (int) $request->validated('grade_id'),
            'learning_language' => $request->validated('learning_language'),
            'timezone' => config('studylikepro.default_display_timezone'),
        ]);
        $profile->completed_at ??= now();
        $profile->save();

        $user->setRelation('studentProfile', $profile);

        $user->interestedSubjects()->sync(array_values($request->validated('basket_subjects') ?? []));

        return redirect()
            ->route('student.dashboard')
            ->with('status', 'profile-completed');
    }

    /*
    |--------------------------------------------------------------------------
    | Wizard data
    |--------------------------------------------------------------------------
    */

    /**
     * The subject baskets of every grade that chooses from baskets, keyed by
     * grade id.
     *
     * @param  Collection<int, EducationLevel>  $levels
     * @return array<int, list<array{basket: SubjectBasket, subjects: Collection<int, Subject>}>>
     */
    private function basketGroups(Collection $levels): array
    {
        $groups = [];

        foreach ($levels as $level) {
            foreach ($level->grades as $grade) {
                if (! $level->requiresBasketSelection($grade)) {
                    continue;
                }

                $baskets = $this->catalog->basketGroupsForGrade($grade);

                if ($baskets->isNotEmpty()) {
                    $groups[$grade->id] = $baskets->all();
                }
            }
        }

        return $groups;
    }

    /**
     * Levels and grades with the number of baskets each grade chooses from,
     * which tells the client which steps exist.
     *
     * @param  Collection<int, EducationLevel>  $levels
     * @param  array<int, list<array{basket: SubjectBasket, subjects: Collection<int, Subject>}>>  $basketGroups
     * @return list<array<string, mixed>>
     */
    private function levelPayload(Collection $levels, array $basketGroups): array
    {
        return $levels->map(fn (EducationLevel $level) => [
            'id' => $level->id,
            'name' => $level->name,
            'icon' => $level->icon,
            'grades' => $level->grades->map(fn (Grade $grade) => [
                'id' => $grade->id,
                'label' => $grade->label,
                'basketCount' => count($basketGroups[$grade->id] ?? []),
            ])->values()->all(),
        ])->values()->all();
    }

    /**
     * Basket key → name pairs. Pairs (not a keyed map) because the client
     * builds a Map from them: PHP renumbers integer array keys, so a keyed map
     * would lose the ids.
     *
     * @param  array<int, list<array{basket: SubjectBasket, subjects: Collection<int, Subject>}>>  $basketGroups
     * @return list<array{0: string, 1: string}>
     */
    private function basketNames(array $basketGroups): array
    {
        $names = [];

        foreach ($basketGroups as $groups) {
            foreach ($groups as $group) {
                $names[] = [$group['basket']->key, $group['basket']->name];
            }
        }

        return $names;
    }

    /**
     * Subject id → name pairs, for the same reason as basketNames().
     *
     * @param  array<int, list<array{basket: SubjectBasket, subjects: Collection<int, Subject>}>>  $basketGroups
     * @return list<array{0: int, 1: string}>
     */
    private function subjectNames(array $basketGroups): array
    {
        $names = [];

        foreach ($basketGroups as $groups) {
            foreach ($groups as $group) {
                foreach ($group['subjects'] as $subject) {
                    $names[] = [$subject->id, $subject->name];
                }
            }
        }

        return $names;
    }

    /**
     * The basket picks of a submit that failed validation, keyed by basket
     * key, so the student does not lose them on the way back.
     *
     * @return array<string, int>
     */
    private function submittedBaskets(): array
    {
        $submitted = old('basket_subjects');

        if (! is_array($submitted)) {
            return [];
        }

        return collect($submitted)
            ->filter(fn (mixed $subjectId) => filled($subjectId))
            ->map(fn (mixed $subjectId) => (int) $subjectId)
            ->all();
    }

    /**
     * The step that owns the validation errors, so a failed submit returns the
     * student to where the fix belongs instead of the first step.
     *
     * @param  array<int, list<array{basket: SubjectBasket, subjects: Collection<int, Subject>}>>  $basketGroups
     */
    private function initialStep(array $basketGroups, int $gradeId): int
    {
        $errors = session('errors');

        if ($errors === null) {
            return 1;
        }

        return match (true) {
            $errors->has('level_id') => 1,
            $errors->has('grade_id') => 2,
            $errors->has('basket_subjects') => isset($basketGroups[$gradeId]) ? 3 : 2,
            $errors->has('learning_language') => 4,
            default => 1,
        };
    }
}
