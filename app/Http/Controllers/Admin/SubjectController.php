<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubjectRequest;
use App\Models\EducationLevel;
use App\Models\Subject;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class SubjectController extends Controller
{
    /**
     * List the catalog with usage counts and the create form.
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'level' => ['nullable', 'string', 'exists:education_levels,key'],
        ]);

        $subjects = Subject::query()
            ->with('educationLevel')
            ->when($filters['level'] ?? null, fn ($query, string $key) => $query->forLevel($key))
            ->withCount(['lessons', 'teacherProfiles'])
            ->ordered()
            ->get();

        return view('admin.subjects.index', [
            'subjects' => $subjects,
            'levels' => EducationLevel::query()->ordered()->withCount('subjects')->get(),
            'filters' => $filters,
        ]);
    }

    /**
     * Create a subject.
     */
    public function store(SubjectRequest $request, ActivityLogger $activity): RedirectResponse
    {
        $subject = Subject::query()->create([
            'education_level_id' => $request->validated('education_level_id'),
            'name' => $request->validated('name'),
            'slug' => $request->validated('slug') ?: Str::slug($request->validated('name')),
            'icon' => $request->validated('icon'),
            'description' => $request->validated('description'),
            'sort_order' => (int) $request->validated('sort_order'),
            'is_active' => $request->boolean('is_active'),
        ]);

        $activity->describe('Created the subject "'.$subject->name.'" ('.$subject->educationLevel?->name.')');

        return redirect()
            ->route('admin.subjects.edit', $subject)
            ->with('status', 'subject-created');
    }

    /**
     * Edit a subject and manage its per-grade lessons.
     */
    public function edit(Request $request, Subject $subject): View
    {
        $subject->load([
            'educationLevel',
            'lessons' => fn ($query) => $query->ordered()->with('grade'),
        ]);

        $grades = $subject->educationLevel?->grades()->ordered()->get() ?? collect();
        $requested = (int) $request->integer('grade');

        return view('admin.subjects.edit', [
            'subject' => $subject,
            'grades' => $grades,
            'lessonsByGrade' => $subject->lessons->groupBy('grade_id'),
            'selectedGradeId' => $grades->contains('id', $requested) ? $requested : null,
        ]);
    }

    /**
     * Update a subject.
     */
    public function update(SubjectRequest $request, Subject $subject, ActivityLogger $activity): RedirectResponse
    {
        $subject->update([
            'name' => $request->validated('name'),
            'slug' => $request->validated('slug') ?: Str::slug($request->validated('name')),
            'icon' => $request->validated('icon'),
            'description' => $request->validated('description'),
            'sort_order' => (int) $request->validated('sort_order'),
            'is_active' => $request->boolean('is_active'),
        ]);

        $activity->describe('Updated the subject "'.$subject->name.'"');

        return redirect()
            ->route('admin.subjects.edit', $subject)
            ->with('status', 'subject-updated');
    }
}
