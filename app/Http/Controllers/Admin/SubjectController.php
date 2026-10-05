<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubjectRequest;
use App\Models\Subject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SubjectController extends Controller
{
    /**
     * List the catalog with usage counts and the create form.
     */
    public function index(): View
    {
        $subjects = Subject::query()
            ->withCount(['topics', 'teacherProfiles'])
            ->ordered()
            ->get();

        return view('admin.subjects.index', ['subjects' => $subjects]);
    }

    /**
     * Create a subject.
     */
    public function store(SubjectRequest $request): RedirectResponse
    {
        $subject = Subject::query()->create([
            'name' => $request->validated('name'),
            'slug' => $request->validated('slug') ?: Str::slug($request->validated('name')),
            'icon' => $request->validated('icon'),
            'description' => $request->validated('description'),
            'sort_order' => (int) $request->validated('sort_order'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('admin.subjects.edit', $subject)
            ->with('status', 'subject-created');
    }

    /**
     * Edit a subject and manage its topics.
     */
    public function edit(Subject $subject): View
    {
        return view('admin.subjects.edit', [
            'subject' => $subject->load(['topics' => fn ($query) => $query->ordered()]),
        ]);
    }

    /**
     * Update a subject.
     */
    public function update(SubjectRequest $request, Subject $subject): RedirectResponse
    {
        $subject->update([
            'name' => $request->validated('name'),
            'slug' => $request->validated('slug') ?: Str::slug($request->validated('name')),
            'icon' => $request->validated('icon'),
            'description' => $request->validated('description'),
            'sort_order' => (int) $request->validated('sort_order'),
            'is_active' => $request->boolean('is_active'),
        ]);

        return redirect()
            ->route('admin.subjects.edit', $subject)
            ->with('status', 'subject-updated');
    }
}
