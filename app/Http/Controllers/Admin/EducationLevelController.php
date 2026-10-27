<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\EducationLevelRequest;
use App\Models\EducationLevel;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The curriculum backbone: the education levels with their grades. Grades are
 * seeded and read-only here; the levels themselves can be renamed, reordered,
 * switched off and deleted.
 */
class EducationLevelController extends Controller
{
    public function index(): View
    {
        $levels = EducationLevel::query()
            ->ordered()
            ->with([
                'grades' => fn ($query) => $query->ordered(),
                'subjectBaskets' => fn ($query) => $query->ordered()->withCount('subjects'),
            ])
            ->withCount('subjects')
            ->get();

        return view('admin.curriculum.index', ['levels' => $levels]);
    }

    public function update(EducationLevelRequest $request, EducationLevel $level, ActivityLogger $activity): RedirectResponse
    {
        $level->update([
            'name' => $request->validated('name'),
            'icon' => $request->validated('icon'),
            'sort_order' => (int) $request->validated('sort_order'),
            'is_active' => $request->boolean('is_active'),
        ]);

        $activity->describe('Updated the '.$level->name.' education level');

        return redirect()
            ->route('admin.curriculum.index')
            ->with('status', 'level-updated');
    }

    /**
     * Delete a level. Its grades and baskets cascade away, but a level that
     * still holds subjects is kept: subject rows are the history every lesson,
     * offering and request hangs off, so they must be moved or deleted first.
     */
    public function destroy(EducationLevel $level, ActivityLogger $activity): RedirectResponse
    {
        $name = $level->name;
        $subjects = $level->subjects()->count();

        if ($subjects > 0) {
            return redirect()
                ->route('admin.curriculum.index')
                ->with('status', 'level-in-use')
                ->with('levelInUse', [
                    'name' => $name,
                    'subjects' => $subjects,
                ]);
        }

        $level->delete();

        $activity->describe('Deleted the '.$name.' education level');

        return redirect()
            ->route('admin.curriculum.index')
            ->with('status', 'level-deleted');
    }
}
