<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Services\SlotService;
use App\Services\TeacherStatsService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The student workspace's view of one teacher: the public profile content,
 * scoped to the grade on the student's learning profile so only the subjects,
 * lessons and rates that apply to them are shown.
 */
class TeacherProfileController extends Controller
{
    public function __invoke(Request $request, TeacherProfile $teacherProfile, SlotService $slots, TeacherStatsService $stats): View
    {
        abort_unless($teacherProfile->isApproved(), 404);

        $teacherProfile->load([
            'user',
            'subjects' => fn ($relation) => $relation->where('subjects.is_active', true)->with([
                'educationLevel.grades' => fn ($query) => $query->where('is_active', true)->ordered(),
            ]),
            'lessons.grade',
        ]);

        $studentGrade = $request->user()->studentProfile?->grade;

        $gradeScoped = false;

        if ($studentGrade !== null) {
            $scopedSubjects = $teacherProfile->subjects
                ->filter(fn (Subject $subject) => in_array((int) $studentGrade->id, $teacherProfile->gradeIdsFor($subject), true))
                ->values();

            if ($scopedSubjects->isNotEmpty()) {
                $teacherProfile->setRelation('subjects', $scopedSubjects);
                $teacherProfile->setRelation('lessons', $teacherProfile->lessons
                    ->where('grade_id', $studentGrade->id)
                    ->values());
                $gradeScoped = true;
            }
        }

        return view('student.teacher', [
            'teacher' => $teacherProfile,
            'lessonsBySubject' => $teacherProfile->lessons->groupBy('subject_id'),
            'availability' => $slots->upcoming($teacherProfile, 14, 40, excludeBookings: true),
            'reviews' => $teacherProfile->visibleReviews()->with('student')->limit(5)->get(),
            'breakdown' => $stats->ratingBreakdown($teacherProfile),
            'studentGrade' => $studentGrade,
            'gradeScoped' => $gradeScoped,
            'viewerTz' => $request->user()->studentProfile?->timezone ?? config('studylikepro.default_display_timezone'),
        ]);
    }
}
