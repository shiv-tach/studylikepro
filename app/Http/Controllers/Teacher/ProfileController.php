<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Concerns\StoresAvatar;
use App\Http\Controllers\Controller;
use App\Http\Requests\TeacherProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileController extends Controller
{
    use StoresAvatar;

    /**
     * Show the teaching profile form (doubles as onboarding step 1).
     */
    public function edit(Request $request): View
    {
        return view('teacher.profile', [
            'profile' => $request->user()->teacherProfile,
        ]);
    }

    /**
     * Create or update the teacher profile.
     */
    public function update(TeacherProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $wasIncomplete = ! $user->teacherProfile?->isComplete();

        $profile = $user->teacherProfile()->firstOrNew([]);
        $profile->fill($request->safe()->except(['avatar', 'hourly_rate']));
        $profile->hourly_rate_minor = (int) round(((float) $request->input('hourly_rate')) * 100);
        $profile->completed_at ??= now();
        $profile->save();

        $user->setRelation('teacherProfile', $profile);

        $this->updateAvatar($request, $user);

        if ($wasIncomplete) {
            return redirect()
                ->route('teacher.verification')
                ->with('status', 'profile-completed');
        }

        return redirect()
            ->route('teacher.profile')
            ->with('status', 'profile-updated');
    }
}
