<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TeacherInvite;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeacherInviteController extends Controller
{
    /**
     * List onboarding links and show the create form.
     */
    public function index(): View
    {
        $invites = TeacherInvite::query()
            ->with('creator')
            ->orderByDesc('created_at')
            ->paginate(20);

        return view('admin.invites.index', [
            'invites' => $invites,
            'defaultExpiryDays' => config('studylikepro.teacher_invite_expiry_days', 7),
        ]);
    }

    /**
     * Create a single-use teacher onboarding link.
     */
    public function store(Request $request, ActivityLogger $activity): RedirectResponse
    {
        $validated = $request->validate([
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        [$invite, $plainToken] = TeacherInvite::createWithToken(
            isset($validated['expires_in_days']) ? (int) $validated['expires_in_days'] : (int) config('studylikepro.teacher_invite_expiry_days', 7),
            $request->user()?->id,
        );

        $activity->describe('Created a teacher onboarding invite (expires '.$invite->expires_at?->toDateString().')');

        return redirect()
            ->route('admin.invites.index')
            ->with('status', 'invite-created')
            ->with('invite_token', $plainToken);
    }

    /**
     * Revoke an unused invite.
     */
    public function revoke(Request $request, TeacherInvite $teacherInvite, ActivityLogger $activity): RedirectResponse
    {
        $teacherInvite->delete();

        $activity->describe('Revoked a teacher onboarding invite');

        return redirect()
            ->route('admin.invites.index')
            ->with('status', 'invite-revoked');
    }
}
