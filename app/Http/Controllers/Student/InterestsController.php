<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\InterestsRequest;
use App\Models\Grade;
use App\Models\Lesson;
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
     * Show the student's subject and lesson interests, limited to their level
     * and grade and grouped by O/L basket where the grade picks one subject
     * per basket.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();
        $grade = $user->studentProfile?->grade;

        $subjects = $grade !== null
            ? $this->catalog->subjectsForGrade($grade)
            : $this->catalog->subjects();

        $baskets = $this->basketsForGrade($grade);

        return view('student.interests', [
            'grade' => $grade,
            'subjects' => $subjects,
            'baskets' => $baskets,
            'subjectGroups' => $this->catalog->groupByBasket($subjects, $baskets),
            'selectedSubjects' => $user->interestedSubjects()->pluck('subjects.id')->all(),
            'selectedLessons' => $user->interestedLessons()->pluck('lessons.id')->all(),
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
     * The active baskets of the student's grade, or an empty set when the
     * grade has no basket choice (every grade except O/L 10-11).
     *
     * @return Collection<int, SubjectBasket>
     */
    private function basketsForGrade(?Grade $grade): Collection
    {
        if ($grade === null || ! $grade->requiresBasketSelection() || $grade->educationLevel === null) {
            return collect();
        }

        return $this->catalog->basketsFor($grade->educationLevel);
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
        $baskets = $this->basketsForGrade($grade);

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
