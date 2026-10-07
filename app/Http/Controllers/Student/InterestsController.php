<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\InterestsRequest;
use App\Models\Lesson;
use App\Services\CatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InterestsController extends Controller
{
    public function __construct(private readonly CatalogService $catalog) {}

    /**
     * Show the student's subject and lesson interests, limited to their level
     * and grade.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();
        $grade = $user->studentProfile?->grade;

        $subjects = $this->catalog->subjects();

        if ($grade !== null) {
            $subjects = $subjects
                ->where('education_level_id', $grade->education_level_id)
                ->values();

            $subjects->each(fn ($subject) => $subject->setRelation(
                'lessons',
                $subject->lessons->where('grade_id', $grade->id)->values(),
            ));
        }

        return view('student.interests', [
            'grade' => $grade,
            'subjects' => $subjects,
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
            ->with('status', 'interests-updated');
    }
}
