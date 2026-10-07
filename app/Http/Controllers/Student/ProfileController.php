<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Concerns\StoresAvatar;
use App\Http\Controllers\Controller;
use App\Http\Requests\StudentProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    use StoresAvatar;

    /**
     * Show the learning profile form (doubles as onboarding).
     */
    public function edit(Request $request): View
    {
        $profile = $request->user()->studentProfile;

        return view('student.profile', [
            'profile' => $profile,
            'isOnboarding' => ! $profile?->isComplete(),
        ]);
    }

    /**
     * Create or update the student profile.
     */
    public function update(StudentProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $wasIncomplete = ! $user->studentProfile?->isComplete();

        $profile = $user->studentProfile()->firstOrNew([]);
        $profile->fill($request->safe()->except('avatar'));
        $profile->timezone = config('studylikepro.default_display_timezone');
        $profile->completed_at ??= now();
        $profile->save();

        $user->setRelation('studentProfile', $profile);

        $this->updateAvatar($request, $user);

        if ($wasIncomplete) {
            return redirect()
                ->route('student.dashboard')
                ->with('status', 'profile-completed');
        }

        return redirect()
            ->route('student.profile')
            ->with('status', 'profile-updated');
    }
}
