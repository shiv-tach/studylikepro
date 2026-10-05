<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubjectSetupRequest;
use App\Http\Requests\TeachingSetupRequest;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubjectsController extends Controller
{
    /**
     * Manage the subjects and topics this teacher teaches.
     */
    public function index(Request $request): View
    {
        $profile = $request->user()->teacherProfile;

        $subjects = Subject::query()
            ->active()
            ->ordered()
            ->with(['topics' => fn ($query) => $query->where('is_active', true)->ordered()])
            ->get();

        return view('teacher.subjects', [
            'subjects' => $subjects,
            'selectedSubjects' => $profile->subjects()->get()->keyBy('id'),
            'selectedTopics' => $profile->topics()->pluck('topics.id')->all(),
        ]);
    }

    /**
     * Sync the teacher's subjects (detaching topics of removed subjects).
     */
    public function store(TeachingSetupRequest $request): RedirectResponse
    {
        $profile = $request->user()->teacherProfile;
        $selected = $request->validated('subjects') ?? [];

        $removed = $profile->subjects()->pluck('subjects.id')->diff($selected);

        if ($removed->isNotEmpty()) {
            $profile->topics()->detach(
                Topic::query()->whereIn('subject_id', $removed)->pluck('id')
            );
        }

        $profile->subjects()->sync($selected);

        return redirect()
            ->route('teacher.subjects.index')
            ->with('status', 'subjects-updated');
    }

    /**
     * Update topics, grade levels, and the rate override for one subject.
     */
    public function update(SubjectSetupRequest $request, Subject $subject): RedirectResponse
    {
        $profile = $request->user()->teacherProfile;

        abort_unless(
            $profile->subjects()->where('subjects.id', $subject->id)->exists(),
            403
        );

        $topics = $request->validated('topics') ?? [];

        $otherTopics = $profile->topics()
            ->where('topics.subject_id', '!=', $subject->id)
            ->pluck('topics.id');

        $profile->topics()->sync($otherTopics->merge($topics)->all());

        $rate = $request->validated('rate_per_hour');

        $profile->subjects()->updateExistingPivot($subject->id, [
            'grade_levels' => $request->validated('grade_levels') ?? [],
            'rate_per_hour_minor' => $rate !== null ? (int) round(((float) $rate) * 100) : null,
        ]);

        return redirect()
            ->route('teacher.subjects.index')
            ->with('status', 'subject-setup-updated');
    }
}
