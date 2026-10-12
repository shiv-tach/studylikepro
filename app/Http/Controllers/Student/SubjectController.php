<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Services\SlotService;
use App\Services\SubjectPageService;
use App\Services\TeacherSearch;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The student workspace's subject page: the same lessons, services and
 * teachers as the public catalog page, scoped to the grade on the student's
 * learning profile, rendered inside the student area.
 */
class SubjectController extends Controller
{
    public function __invoke(Request $request, Subject $subject, TeacherSearch $search, SlotService $slots, SubjectPageService $pages): View
    {
        abort_unless($subject->is_active, 404);

        // The profile grade is the only scope: the query string cannot widen
        // the page to another grade.
        $level = $pages->levelFor($subject);
        $grade = $pages->profileGrade($level, $request->user()->studentProfile?->grade);

        return view('student.subject', $pages->payload($subject, $grade, $search, $slots));
    }
}
