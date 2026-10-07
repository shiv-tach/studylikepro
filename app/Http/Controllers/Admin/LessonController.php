<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\CopyLessonsRequest;
use App\Http\Requests\LessonRequest;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Subject;
use App\Services\ActivityLogger;
use App\Services\CatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Lesson CRUD inside one subject, plus the bulk tools of the grade matrix:
 * copy a grade's lessons into another grade, flip a whole grade on or off, and
 * nudge one lesson up or down the order.
 */
class LessonController extends Controller
{
    /**
     * Add a lesson to a subject.
     */
    public function store(LessonRequest $request, Subject $subject, ActivityLogger $activity): RedirectResponse
    {
        $lesson = $subject->lessons()->create([
            'grade_id' => $request->validated('grade_id'),
            'name' => $request->validated('name'),
            'slug' => $request->validated('slug') ?: Str::slug($request->validated('name')),
            'description' => $request->validated('description'),
            'sort_order' => (int) $request->validated('sort_order'),
            'is_active' => $request->boolean('is_active'),
        ]);

        $activity->describe('Added "'.$lesson->name.'" to '.($lesson->grade?->label ?? 'a grade').' of "'.$subject->name.'"');

        return redirect()
            ->route('admin.subjects.edit', ['subject' => $subject, 'grade' => $lesson->grade_id])
            ->with('status', 'lesson-created');
    }

    /**
     * Update a lesson.
     */
    public function update(LessonRequest $request, Subject $subject, Lesson $lesson, ActivityLogger $activity): RedirectResponse
    {
        $previousGrade = $lesson->grade_id;

        $lesson->update([
            'grade_id' => $request->validated('grade_id'),
            'name' => $request->validated('name'),
            'slug' => $request->validated('slug') ?: Str::slug($request->validated('name')),
            'description' => $request->validated('description'),
            'sort_order' => (int) $request->validated('sort_order'),
            'is_active' => $request->boolean('is_active'),
        ]);

        $activity->describe('Updated "'.$lesson->name.'" in '.($lesson->grade?->label ?? 'a grade').' of "'.$subject->name.'"');

        return redirect()
            ->route('admin.subjects.edit', ['subject' => $subject, 'grade' => $lesson->grade_id])
            ->with('status', $lesson->grade_id === $previousGrade ? 'lesson-updated' : 'lesson-moved-grade');
    }

    /**
     * Remove a lesson.
     */
    public function destroy(Subject $subject, Lesson $lesson, ActivityLogger $activity): RedirectResponse
    {
        $name = $lesson->name;
        $gradeId = $lesson->grade_id;

        $lesson->delete();

        $activity->describe('Removed "'.$name.'" from "'.$subject->name.'"');

        return redirect()
            ->route('admin.subjects.edit', ['subject' => $subject, 'grade' => $gradeId])
            ->with('status', 'lesson-removed');
    }

    /**
     * Copy every lesson of one grade into another grade, skipping the slugs that
     * already exist there (so a second copy never duplicates anything).
     */
    public function copy(CopyLessonsRequest $request, Subject $subject, ActivityLogger $activity): RedirectResponse
    {
        $from = $request->integer('from_grade_id');
        $to = $request->integer('to_grade_id');

        $existingSlugs = $subject->lessons()->where('grade_id', $to)->pluck('slug')->all();
        $copied = 0;

        foreach ($subject->lessons()->where('grade_id', $from)->ordered()->get() as $lesson) {
            if (in_array($lesson->slug, $existingSlugs, true)) {
                continue;
            }

            $subject->lessons()->create([
                'grade_id' => $to,
                'name' => $lesson->name,
                'slug' => $lesson->slug,
                'description' => $lesson->description,
                'sort_order' => $lesson->sort_order,
                'is_active' => $lesson->is_active,
            ]);

            $copied++;
        }

        $fromLabel = Grade::query()->whereKey($from)->value('label');
        $toLabel = Grade::query()->whereKey($to)->value('label');

        $activity->describe(sprintf(
            'Copied %d %s from %s to %s in "%s"',
            $copied,
            Str::plural('lesson', $copied),
            $fromLabel ?? 'a grade',
            $toLabel ?? 'another grade',
            $subject->name,
        ));

        return redirect()
            ->route('admin.subjects.edit', ['subject' => $subject, 'grade' => $to])
            ->with('status', 'lessons-copied')
            ->with('copied', $copied);
    }

    /**
     * Activate or deactivate every lesson of one grade.
     */
    public function bulkActive(Request $request, Subject $subject, ActivityLogger $activity, CatalogService $catalog): RedirectResponse
    {
        $validated = $request->validate([
            'grade_id' => [
                'required',
                'integer',
                Rule::exists('grades', 'id')->where('education_level_id', $subject->education_level_id),
            ],
            'is_active' => ['required', 'boolean'],
        ]);

        $updated = $subject->lessons()
            ->where('grade_id', $validated['grade_id'])
            ->update(['is_active' => (bool) $validated['is_active']]);

        // The mass update fires no model events, so the cached catalog the
        // public pages read would otherwise keep the old activation state.
        $catalog->flush();

        $gradeLabel = Grade::query()->whereKey($validated['grade_id'])->value('label');

        $activity->describe(sprintf(
            '%s %d %s in %s of "%s"',
            $validated['is_active'] ? 'Activated' : 'Deactivated',
            $updated,
            Str::plural('lesson', $updated),
            $gradeLabel ?? 'a grade',
            $subject->name,
        ));

        return redirect()
            ->route('admin.subjects.edit', ['subject' => $subject, 'grade' => $validated['grade_id']])
            ->with('status', 'lessons-status-updated')
            ->with('updated', $updated);
    }

    /**
     * Move a lesson one position up or down inside its grade, renumbering the
     * grade's order so ties resolve deterministically.
     */
    public function move(Request $request, Subject $subject, Lesson $lesson, ActivityLogger $activity): RedirectResponse
    {
        $validated = $request->validate([
            'direction' => ['required', 'string', 'in:up,down'],
        ]);

        $lessons = $subject->lessons()
            ->where('grade_id', $lesson->grade_id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->values();

        $index = $lessons->search(fn (Lesson $item) => $item->is($lesson));

        if ($index === false) {
            return back();
        }

        $target = $validated['direction'] === 'up' ? $index - 1 : $index + 1;

        if (! $lessons->has($target)) {
            return back();
        }

        $ordered = $lessons->all();
        [$ordered[$index], $ordered[$target]] = [$ordered[$target], $ordered[$index]];

        foreach ($ordered as $position => $item) {
            if ($item->sort_order !== $position + 1) {
                $item->update(['sort_order' => $position + 1]);
            }
        }

        $activity->describe('Reordered "'.$lesson->name.'" in '.($lesson->grade?->label ?? 'its grade'));

        return redirect()
            ->route('admin.subjects.edit', ['subject' => $subject, 'grade' => $lesson->grade_id])
            ->with('status', 'lessons-reordered');
    }
}
