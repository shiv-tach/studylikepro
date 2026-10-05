<?php

namespace App\Http\Controllers;

use App\Http\Requests\TeacherSearchRequest;
use App\Models\TeacherProfile;
use App\Services\CatalogService;
use App\Services\SlotService;
use App\Services\TeacherSearch;
use App\Services\TeacherStatsService;
use Illuminate\View\View;

class TeacherDirectoryController extends Controller
{
    /**
     * Public teacher listing with filters and sorting.
     */
    public function index(TeacherSearchRequest $request, TeacherSearch $search, SlotService $slots, CatalogService $catalog): View
    {
        $filters = $request->validated();
        $teachers = $search->paginate($filters);

        $nextSlots = [];

        foreach ($teachers as $teacher) {
            $nextSlots[$teacher->id] = $slots->upcoming($teacher, 7, 1, excludeBookings: true)[0] ?? null;
        }

        return view('teachers.index', [
            'teachers' => $teachers,
            'nextSlots' => $nextSlots,
            'filters' => $filters,
            'subjects' => $catalog->subjects(),
            'sort' => $request->sort(),
        ]);
    }

    /**
     * Public teacher profile with availability preview and recent reviews.
     */
    public function show(TeacherProfile $teacherProfile, SlotService $slots, TeacherStatsService $stats): View
    {
        abort_unless($teacherProfile->isApproved(), 404);

        $teacherProfile->load([
            'user',
            'subjects' => fn ($relation) => $relation->where('subjects.is_active', true),
            'topics',
        ]);

        return view('teachers.show', [
            'teacher' => $teacherProfile,
            'topicsBySubject' => $teacherProfile->topics->groupBy('subject_id'),
            'availability' => $slots->upcoming($teacherProfile, 14, 40, excludeBookings: true),
            'reviews' => $teacherProfile->visibleReviews()->with('student')->limit(5)->get(),
            'breakdown' => $stats->ratingBreakdown($teacherProfile),
        ]);
    }
}
