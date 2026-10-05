<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\InterestsRequest;
use App\Models\Topic;
use App\Services\CatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InterestsController extends Controller
{
    public function __construct(private readonly CatalogService $catalog) {}

    /**
     * Show the student's subject and topic interests.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('student.interests', [
            'subjects' => $this->catalog->subjects(),
            'selectedSubjects' => $user->interestedSubjects()->pluck('subjects.id')->all(),
            'selectedTopics' => $user->interestedTopics()->pluck('topics.id')->all(),
        ]);
    }

    /**
     * Sync the student's interests (topics limited to selected subjects).
     */
    public function update(InterestsRequest $request): RedirectResponse
    {
        $user = $request->user();
        $subjectIds = $request->validated('subjects') ?? [];
        $topicIds = $request->validated('topics') ?? [];

        // Keep only topics that belong to one of the selected subjects.
        $validTopicIds = $topicIds === [] ? [] : Topic::query()
            ->whereIn('id', $topicIds)
            ->whereIn('subject_id', $subjectIds)
            ->pluck('id')
            ->all();

        $user->interestedSubjects()->sync($subjectIds);
        $user->interestedTopics()->sync($validTopicIds);

        return redirect()
            ->route('student.interests.edit')
            ->with('status', 'interests-updated');
    }
}
