<?php

namespace App\Http\Controllers;

use App\Models\EducationLevel;
use App\Models\Grade;
use App\Models\Subject;
use App\Services\CatalogService;
use App\Services\SlotService;
use App\Services\TeacherSearch;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    /** Teacher cards a subject page previews before linking to the directory. */
    private const TEACHER_PREVIEW = 6;

    public function __construct(private readonly CatalogService $catalog) {}

    /**
     * Public subject browse: level, then grade, then the subjects of that grade.
     */
    public function index(Request $request): View
    {
        $levels = $this->catalog->levels();
        $levelKey = $request->query('level');
        $level = is_string($levelKey) ? $levels->firstWhere('key', $levelKey) : null;
        $grade = $this->requestedGrade($request, $level);

        return view('catalog.index', [
            'levels' => $levels,
            'level' => $level,
            'grade' => $grade,
            'subjects' => $grade === null ? collect() : $this->catalog->subjectsForGrade($grade),
        ]);
    }

    /**
     * Public lessons for one subject, scoped to a grade when the visitor
     * arrived through the drill-down, plus the teachers who teach it.
     */
    public function show(Subject $subject, Request $request, TeacherSearch $search, SlotService $slots): View
    {
        abort_unless($subject->is_active, 404);

        $levels = $this->catalog->levels();
        $level = $levels->firstWhere('id', $subject->education_level_id);
        $grade = $this->requestedGrade($request, $level);

        $lessons = $this->catalog->lessonsFor($subject);

        if ($grade !== null) {
            $lessons = $lessons->where('grade_id', $grade->id)->values();
        }

        $filters = array_filter([
            'subject' => $subject->slug,
            'grade' => $grade?->id,
        ], fn ($value) => $value !== null);

        $teachers = $search->query($filters)->limit(self::TEACHER_PREVIEW)->get();

        $nextSlots = [];

        foreach ($teachers as $teacher) {
            $nextSlots[$teacher->id] = $slots->upcoming($teacher, 7, 1, excludeBookings: true)[0] ?? null;
        }

        return view('catalog.show', [
            'subject' => $subject,
            'level' => $level,
            'grade' => $grade,
            'lessons' => $lessons,
            'teachers' => $teachers,
            'teacherTotal' => $search->query($filters)->count(),
            'nextSlots' => $nextSlots,
            'gradesById' => $levels->flatMap(fn (EducationLevel $option) => $option->grades)->keyBy('id'),
        ]);
    }

    /**
     * The active grade the URL asked for - only when it belongs to the level.
     */
    private function requestedGrade(Request $request, ?EducationLevel $level): ?Grade
    {
        $gradeId = $request->query('grade');

        if ($level === null || ! is_numeric($gradeId)) {
            return null;
        }

        return $level->grades->firstWhere('id', (int) $gradeId);
    }
}
