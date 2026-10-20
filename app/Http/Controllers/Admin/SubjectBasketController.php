<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubjectBasketRequest;
use App\Models\SubjectBasket;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

/**
 * The optional-subject baskets of a level (the O/L's three categories) are
 * seeded and fixed in number; an admin can rename, reorder, switch them off or
 * delete them, and assigns subjects to them from the subject editor.
 */
class SubjectBasketController extends Controller
{
    public function update(SubjectBasketRequest $request, SubjectBasket $basket, ActivityLogger $activity): RedirectResponse
    {
        $basket->update([
            'name' => $request->validated('name'),
            'description' => $request->validated('description'),
            'icon' => $request->validated('icon'),
            'sort_order' => (int) $request->validated('sort_order'),
            'is_active' => $request->boolean('is_active'),
        ]);

        $activity->describe('Updated the '.$basket->name.' basket ('.$basket->educationLevel?->name.')');

        return redirect()
            ->route('admin.curriculum.index')
            ->with('status', 'basket-updated');
    }

    /**
     * Delete a basket. Its subjects are kept and simply stop belonging to a
     * basket, so no catalog entry disappears with it.
     */
    public function destroy(SubjectBasket $basket, ActivityLogger $activity): RedirectResponse
    {
        $name = $basket->name;
        $level = $basket->educationLevel?->name;
        $subjects = $basket->subjects()->count();

        $basket->delete();

        $activity->describe(
            'Deleted the '.$name.' basket'.($level !== null ? ' ('.$level.')' : '')
            .($subjects > 0 ? ' and released '.$subjects.' '.Str::plural('subject', $subjects) : '')
        );

        return redirect()
            ->route('admin.curriculum.index')
            ->with('status', 'basket-deleted');
    }
}
