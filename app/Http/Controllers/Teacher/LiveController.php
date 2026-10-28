<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The one-time "You're live" announcement. It is owed from the moment an admin
 * approves the application until the teacher acknowledges it, so the completion
 * moment happens when the promise is actually fulfilled.
 */
class LiveController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $profile = $request->user()->teacherProfile;

        if (! $profile?->isApproved()) {
            return redirect()->route('teacher.approval.status');
        }

        // Nothing left to announce: a bookmark or a back button should not
        // replay the celebration.
        if (! $profile->owesLiveAnnouncement()) {
            return redirect()->route('teacher.dashboard');
        }

        return view('teacher.live', [
            'profile' => $profile,
            'approvedSubjects' => $profile->subjects()
                ->wherePivot('status', \App\Enums\ApprovalStatus::Approved)
                ->pluck('subjects.name')
                ->all(),
            'publicUrl' => route('teachers.show', $profile),
        ]);
    }

    /**
     * The teacher has read the announcement, so it is never shown again.
     */
    public function acknowledge(Request $request): RedirectResponse
    {
        $profile = $request->user()->teacherProfile;

        if ($profile?->isApproved() && $profile->owesLiveAnnouncement()) {
            $profile->forceFill(['approved_seen_at' => now()])->save();
        }

        return redirect()
            ->route('teacher.dashboard')
            ->with('status', 'teacher-live');
    }
}
