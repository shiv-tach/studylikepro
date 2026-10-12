<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\InterestsRequest;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Subject;
use App\Models\SubjectBasket;
use App\Services\CatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class InterestsController extends Controller
{
    public function __construct(private readonly CatalogService $catalog) {}

    /**
     * The subjects the student is focusing on: every compulsory subject of
     * their grade plus the basket subjects they picked, each a card that opens
     * the dedicated subject page. The picker itself lives in onboarding; this
     * page is a display, not a form.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();
        $grade = $user->studentProfile?->grade;

        $subjects = $grade !== null
            ? $this->catalog->subjectsForGrade($grade)
            : $this->catalog->subjects();

        $baskets = $grade !== null ? $this->catalog->basketsForGrade($grade) : collect();

        return view('student.interests', [
            'grade' => $grade,
            'baskets' => $baskets,
            'subjectGroups' => $this->focusGroups(
                $subjects,
                $baskets,
                $user->interestedSubjects()->pluck('subjects.id')->all(),
            ),
        ]);
    }

    /**
     * Sync the student's interests. Lessons limited to the selected subjects
     * and to the student's grade — anything else is dropped.
     */
    public function update(InterestsRequest $request): RedirectResponse
    {
        $user = $request->user();
        $subjectIds = $request->validated('subjects') ?? [];
        $lessonIds = $request->validated('lessons') ?? [];

        $grade = $user->studentProfile?->grade;

        $validLessonIds = $lessonIds === [] ? [] : Lesson::query()
            ->whereIn('id', $lessonIds)
            ->whereIn('subject_id', $subjectIds)
            ->when($grade !== null, fn ($query) => $query->where('grade_id', $grade->id))
            ->pluck('id')
            ->all();

        $user->interestedSubjects()->sync($subjectIds);
        $user->interestedLessons()->sync($validLessonIds);

        return redirect()
            ->route('student.interests.edit')
            ->with('status', 'interests-updated')
            ->with('basketReminder', $this->missingBaskets($grade, $subjectIds));
    }

    /**
     * The page groups: every compulsory subject first, then each basket
     * carrying only the subjects the student picked from it. Empty baskets are
     * dropped, so the page shows exactly what the student is focusing on.
     *
     * @param  Collection<int, Subject>  $subjects
     * @param  Collection<int, SubjectBasket>  $baskets
     * @param  array<int, int>  $selectedSubjectIds
     * @return Collection<int, array{basket: SubjectBasket|null, subjects: Collection<int, Subject>}>
     */
    private function focusGroups(Collection $subjects, Collection $baskets, array $selectedSubjectIds): Collection
    {
        $basketIds = $baskets->pluck('id');

        $groups = collect([
            [
                'basket' => null,
                'subjects' => $subjects
                    ->reject(fn (Subject $subject) => $basketIds->contains($subject->basket_id))
                    ->values(),
            ],
        ]);

        foreach ($baskets as $basket) {
            $groups->push([
                'basket' => $basket,
                'subjects' => $subjects
                    ->where('basket_id', $basket->id)
                    ->filter(fn (Subject $subject) => in_array($subject->id, $selectedSubjectIds, true))
                    ->values(),
            ]);
        }

        return $groups
            ->filter(fn (array $group) => $group['subjects']->isNotEmpty())
            ->values();
    }

    /**
     * Names of the baskets the student's grade offers but nothing was picked
     * from — the interests page turns this into a friendly reminder, it never
     * blocks the save.
     *
     * @param  array<int, int>  $subjectIds
     * @return list<string>
     */
    private function missingBaskets(?Grade $grade, array $subjectIds): array
    {
        if ($grade === null) {
            return [];
        }

        $baskets = $this->catalog->basketsForGrade($grade);

        if ($baskets->isEmpty()) {
            return [];
        }

        $offered = $this->catalog->subjectsForGrade($grade)
            ->whereIn('basket_id', $baskets->pluck('id'));

        $selected = $offered->whereIn('id', $subjectIds)->pluck('basket_id');

        return $baskets
            ->whereIn('id', $offered->pluck('basket_id')->unique())
            ->reject(fn (SubjectBasket $basket) => $selected->contains($basket->id))
            ->pluck('name')
            ->values()
            ->all();
    }
}
