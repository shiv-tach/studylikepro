<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\TopicRequest;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class TopicController extends Controller
{
    /**
     * Add a topic to a subject.
     */
    public function store(TopicRequest $request, Subject $subject): RedirectResponse
    {
        $subject->topics()->create([
            'name' => $request->validated('name'),
            'slug' => $request->validated('slug') ?: Str::slug($request->validated('name')),
            'sort_order' => (int) $request->validated('sort_order'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('admin.subjects.edit', $subject)
            ->with('status', 'topic-created');
    }

    /**
     * Update a topic.
     */
    public function update(TopicRequest $request, Subject $subject, Topic $topic): RedirectResponse
    {
        $topic->update([
            'name' => $request->validated('name'),
            'slug' => $request->validated('slug') ?: Str::slug($request->validated('name')),
            'sort_order' => (int) $request->validated('sort_order'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('admin.subjects.edit', $subject)
            ->with('status', 'topic-updated');
    }

    /**
     * Remove a topic.
     */
    public function destroy(Subject $subject, Topic $topic): RedirectResponse
    {
        $topic->delete();

        return redirect()
            ->route('admin.subjects.edit', $subject)
            ->with('status', 'topic-removed');
    }
}
