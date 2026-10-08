<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeacherSearchRequest;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Services\CatalogService;
use App\Services\SlotService;
use App\Services\TeacherSearch;
use App\Services\TeacherStatsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeacherDirectoryController extends Controller
{
    /**
     * Public teacher listing with filters and sorting. Students browse through
     * their own grade-matched finder instead.
     */
    public function index(TeacherSearchRequest $request, TeacherSearch $search, SlotService $slots, CatalogService $catalog): View|RedirectResponse
    {
        if ($request->user()?->isStudent()) {
            return redirect()->route('student.teachers.index');
        }

        $filters = $request->validated();
        $teachers = $search->paginate($filters);
        $levels = $catalog->levels();

        $nextSlots = [];

        foreach ($teachers as $teacher) {
            $nextSlots[$teacher->id] = $slots->upcoming($teacher, 7, 1, excludeBookings: true)[0] ?? null;
        }

        return view('teachers.index', [
            'teachers' => $teachers,
            'nextSlots' => $nextSlots,
            'filters' => $filters,
            'subjects' => $catalog->subjects(),
            'levels' => $levels,
            'gradesById' => $levels->flatMap(fn ($level) => $level->grades)->keyBy('id'),
            'sort' => $request->sort(),
        ]);
    }

    /**
     * Public teacher profile with availability preview and recent reviews.
     * Students read the same profile inside their workspace, scoped to the
     * grade on their learning profile.
     */
    public function show(Request $request, TeacherProfile $teacherProfile, SlotService $slots, TeacherStatsService $stats): View|RedirectResponse
    {
        abort_unless($teacherProfile->isApproved(), 404);

        if ($request->user()?->isStudent()) {
            return redirect()->route('student.teachers.show', $teacherProfile);
        }

        $teacherProfile->load([
            'user',
            'subjects' => fn ($relation) => $relation->where('subjects.is_active', true)->with([
                'educationLevel.grades' => fn ($query) => $query->where('is_active', true)->ordered(),
            ]),
            'lessons.grade',
        ]);

        return view('teachers.show', [
            'teacher' => $teacherProfile,
            'lessonsBySubject' => $teacherProfile->lessons->groupBy('subject_id'),
            'gradesById' => $teacherProfile->subjects
                ->flatMap(fn (Subject $subject) => $subject->educationLevel?->grades ?? collect())
                ->keyBy('id'),
            'availability' => $slots->upcoming($teacherProfile, 14, 40, excludeBookings: true),
            'reviews' => $teacherProfile->visibleReviews()->with('student')->limit(5)->get(),
            'breakdown' => $stats->ratingBreakdown($teacherProfile),
        ]);
    }
}
