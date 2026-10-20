<?php

namespace App\Http\Controllers;

use App\Models\Subject;
use App\Services\CatalogService;
use App\Services\SlotService;
use App\Services\SubjectPageService;
use App\Services\TeacherSearch;
use App\Support\PublicCatalogConfig;
use App\Support\PublicSubjectPageConfig;
use Illuminate\Http\JsonResponse;
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
        $state = $this->state($request);

        return view('catalog.index', [
            ...$state,
            'config' => PublicCatalogConfig::build($state),
        ]);
    }

    /**
     * The same browse state as an island, so the sticky explorer can swap the
     * grades and subjects in place without reloading the document.
     */
    public function indexData(Request $request): JsonResponse
    {
        $state = $this->state($request);

        $url = route('catalog.subjects.index', array_filter([
            'level' => $state['level']?->key,
            'grade' => $state['grade']?->id,
        ]));

        return response()->json(PublicCatalogConfig::state(
            $state,
            $url,
            config('app.name', 'Studylikepro').' — '.__('Explore subjects'),
        ));
    }

    /**
     * The catalog read behind both the page and its island.
     *
     * @return array{levels: \Illuminate\Support\Collection<int, mixed>, level: mixed, grade: mixed, gradeCounts: array<int, int>, baskets: \Illuminate\Support\Collection<int, mixed>, groupedByBasket: bool, subjectGroups: \Illuminate\Support\Collection<int, mixed>}
     */
    private function state(Request $request): array
    {
        $levels = $this->catalog->levels();
        $levelKey = $request->query('level');
        $level = is_string($levelKey) ? $levels->firstWhere('key', $levelKey) : null;
        $grade = $this->pages->requestedGrade($request, $level);

        // The grade cards only render while a level is open and no grade is
        // picked, so the counts are only read there - a results page pays for
        // its one grade instead of every grade of the level.
        $gradeCounts = $level !== null && $grade === null ? $this->catalog->gradeSubjectCounts($level) : [];

        $subjects = $grade === null ? collect() : $this->catalog->subjectsForGrade($grade);
        $baskets = $grade === null ? collect() : $this->catalog->basketsForGrade($grade);

        // The basket groups only mean something where the choice happens and
        // the baskets actually carry subjects (the O/L: Category I from Grade
        // 6, all three baskets in Grades 10-11). Everywhere else the grid
        // stays flat and a note says when the baskets begin.
        $groupedByBasket = $baskets->isNotEmpty()
            && $subjects->contains(fn (Subject $subject) => $baskets->contains('id', $subject->basket_id));

        return [
            'levels' => $levels,
            'level' => $level,
            'grade' => $grade,
            'gradeCounts' => $gradeCounts,
            'baskets' => $baskets,
            'groupedByBasket' => $groupedByBasket,
            'subjectGroups' => $this->catalog->groupByBasket($subjects, $groupedByBasket ? $baskets : collect()),
        ];
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

        $payload = $this->pages->payload(
            $subject,
            $this->pages->requestedGrade($request, $this->pages->levelFor($subject)),
            $search,
            $slots,
        );

        return view('catalog.show', [
            ...$payload,
            'config' => PublicSubjectPageConfig::build($subject, $payload),
        ]);
    }

    /**
     * The same page as an island, so the catalog opens a subject on click
     * without reloading the document.
     */
    public function showData(Subject $subject, Request $request, TeacherSearch $search, SlotService $slots): JsonResponse|RedirectResponse
    {
        abort_unless($subject->is_active, 404);

        if ($request->user()?->isStudent()) {
            return redirect()->route('student.subjects.show', array_filter([
                'subject' => $subject->slug,
                'grade' => $request->query('grade'),
            ]));
        }

        $payload = $this->pages->payload(
            $subject,
            $this->pages->requestedGrade($request, $this->pages->levelFor($subject)),
            $search,
            $slots,
        );
        $config = PublicSubjectPageConfig::build($subject, $payload);

        return response()->json([
            'url' => route('catalog.subjects.show', array_filter([
                'subject' => $subject,
                'grade' => $payload['grade']?->id,
            ])),
            'documentTitle' => $config['documentTitle'],
            'config' => $config,
        ]);
    }
}
