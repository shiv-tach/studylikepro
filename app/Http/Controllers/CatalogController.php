<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Services\CatalogService;
use App\Services\SlotService;
use App\Services\SubjectPageService;
use App\Services\TeacherSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    public function __construct(
        private readonly CatalogService $catalog,
        private readonly SubjectPageService $pages,
    ) {}

    /**
     * Public subject browse: level, then grade, then the subjects of that grade.
     */
    public function index(Request $request): View
    {
        $levels = $this->catalog->levels();
        $levelKey = $request->query('level');
        $level = is_string($levelKey) ? $levels->firstWhere('key', $levelKey) : null;
        $grade = $this->pages->requestedGrade($request, $level);

        $subjects = $grade === null ? collect() : $this->catalog->subjectsForGrade($grade);
        $baskets = $grade === null ? collect() : $this->catalog->basketsForGrade($grade);

        // The basket groups only mean something where the choice happens and
        // the baskets actually carry subjects (the O/L: Category I from Grade
        // 6, all three baskets in Grades 10-11). Everywhere else the grid
        // stays flat and a note says when the baskets begin.
        $groupedByBasket = $baskets->isNotEmpty()
            && $subjects->contains(fn (Subject $subject) => $baskets->contains('id', $subject->basket_id));

        return view('catalog.index', [
            'levels' => $levels,
            'level' => $level,
            'grade' => $grade,
            'subjects' => $subjects,
            'baskets' => $baskets,
            'groupedByBasket' => $groupedByBasket,
            'subjectGroups' => $this->catalog->groupByBasket($subjects, $groupedByBasket ? $baskets : collect()),
        ]);
    }

    /**
     * Public lessons for one subject, scoped to a grade when the visitor
     * arrived through the drill-down, plus the teachers who teach it.
     * Signed-in students get their own workspace page instead.
     */
    public function show(Subject $subject, Request $request, TeacherSearch $search, SlotService $slots): View|RedirectResponse
    {
        abort_unless($subject->is_active, 404);

        if ($request->user()?->isStudent()) {
            return redirect()->route('student.subjects.show', array_filter([
                'subject' => $subject->slug,
                'grade' => $request->query('grade'),
            ]));
        }

        $level = $this->pages->levelFor($subject);
        $grade = $this->pages->requestedGrade($request, $level);

        return view('catalog.show', $this->pages->payload($subject, $grade, $search, $slots));
    }
}
