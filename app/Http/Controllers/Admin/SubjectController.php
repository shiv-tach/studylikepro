<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GradeBand;
use App\Http\Controllers\Controller;
use App\Http\Requests\SubjectRequest;
use App\Models\EducationLevel;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\SubjectBasket;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
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

        $levels = EducationLevel::query()->ordered()->withCount('subjects')->get();
        $selectedLevel = ($filters['level'] ?? null) !== null
            ? $levels->firstWhere('key', $filters['level'])
            : null;

        $subjects = Subject::query()
            ->with(['educationLevel', 'basket'])
            ->when($selectedLevel, fn ($query) => $query->forLevel($selectedLevel))
            ->withCount(['lessons', 'teacherProfiles'])
            ->ordered()
            ->get();

        $baskets = SubjectBasket::query()
            ->with('educationLevel')
            ->withCount('subjects')
            ->ordered()
            ->get();

        return view('admin.subjects.index', [
            'subjects' => $subjects,
            'levels' => $levels,
            'baskets' => $baskets,
            // The band labels are relative to a level, so the create form's
            // "Grades" select follows the level that is picked.
            'bandLabels' => $levels->mapWithKeys(fn (EducationLevel $level) => [
                $level->id => collect(GradeBand::cases())
                    ->mapWithKeys(fn (GradeBand $band) => [
                        $band->value => $band->labelWithin($level->grade_min, $level->grade_max),
                    ])
                    ->all(),
            ])->all(),
            // The selected level's own baskets drive the membership manager, so
            // an admin groups its optional subjects without opening each editor.
            'levelBaskets' => $selectedLevel === null
                ? collect()
                : $baskets->where('education_level_id', $selectedLevel->id)->values(),
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
            'basket_id' => $request->validated('basket_id'),
            'grade_band' => $request->validated('grade_band') ?? GradeBand::All,
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
            'basket',
            'lessons' => fn ($query) => $query->ordered()->with('grade'),
        ]);

        $level = $subject->educationLevel;
        $band = $subject->gradeBand();

        // The band narrows a subject to one end of its level, so the lesson
        // matrix only offers the grades the subject is actually taught in.
        $grades = ($level?->grades()->ordered()->get() ?? collect())
            ->filter(fn (Grade $grade) => $band->includes($grade->number))
            ->values();

        $lessons = $subject->lessons->whereIn('grade_id', $grades->pluck('id'));
        $requested = (int) $request->integer('grade');

        return view('admin.subjects.edit', [
            'subject' => $subject,
            'grades' => $grades,
            'lessons' => $lessons,
            'baskets' => $level?->subjectBaskets()->ordered()->get() ?? collect(),
            'lessonsByGrade' => $lessons->groupBy('grade_id'),
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
            'basket_id' => $request->validated('basket_id'),
            'grade_band' => $request->validated('grade_band') ?? GradeBand::All,
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

    /**
     * Delete a subject. Its lessons, teacher selections and student interests
     * cascade away; bookings detach via their nullOnDelete foreign keys so their
     * history survives.
     */
    public function destroy(Subject $subject, ActivityLogger $activity): RedirectResponse
    {
        $name = $subject->name;
        $level = $subject->educationLevel?->name;

        $subject->delete();

        $activity->describe('Deleted the subject "'.$name.'"'.($level !== null ? ' ('.$level.')' : ''));

        return redirect()
            ->route('admin.subjects.index')
            ->with('status', 'subject-deleted');
    }

    /**
     * Move a subject into, between or out of its level's optional-subject
     * baskets from the catalog's basket manager, without opening the editor.
     */
    public function updateBasket(Request $request, Subject $subject, ActivityLogger $activity): RedirectResponse
    {
        $validated = $request->validate([
            'basket_id' => [
                'nullable',
                'integer',
                // Only a basket of the subject's own level may be chosen.
                Rule::exists('subject_baskets', 'id')->where('education_level_id', $subject->education_level_id),
            ],
        ]);

        $subject->update(['basket_id' => $validated['basket_id'] ?? null]);

        $basket = $subject->basket()->first();
        $activity->describe(
            $basket === null
                ? 'Removed the subject "'.$subject->name.'" from its subject basket'
                : 'Moved the subject "'.$subject->name.'" into the '.$basket->name.' basket'
        );

        $levelKey = $subject->educationLevel?->key;

        return redirect()
            ->route('admin.subjects.index', $levelKey !== null ? ['level' => $levelKey] : [])
            ->with('status', 'subject-basket-updated');
    }
}
