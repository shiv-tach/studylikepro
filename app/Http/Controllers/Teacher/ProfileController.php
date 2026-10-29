<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Concerns\StoresAvatar;
use App\Http\Controllers\Controller;
use App\Http\Requests\TeacherProfileRequest;
use App\Services\TeacherOnboardingService;
use App\Support\TeacherProfileConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use Illuminate\View\View;

class ProfileController extends Controller
{
    use StoresAvatar;

    public function __construct(private readonly TeacherOnboardingService $profile) {}

    /**
     * The workspace teaching profile: one form covering the headline, bio,
     * education, languages, hourly rate and photo, with a live preview of the
     * public directory card.
     */
    public function edit(Request $request): View
    {
        $user = $request->user();

        // The preview card shows the teacher's subjects exactly as the public
        // directory does, so they are loaded up front instead of lazily.
        $user->loadMissing('teacherProfile.subjects');

        return view('teacher.profile', [
            'profile' => $user->teacherProfile,
            'config' => TeacherProfileConfig::build(
                $user,
                $user->teacherProfile,
                $request->session()->get('errors')?->getBag('default') ?? new MessageBag,
                $user->theme['preset'] ?? 'classic',
            ),
        ]);
    }

    /**
     * Update the whole profile.
     */
    public function update(TeacherProfileRequest $request): RedirectResponse
    {
        $user = $request->user();

        $this->profile->saveWholeProfile($user, $request->safe()->except(['avatar']));
        $this->updateAvatar($request, $user);

        return redirect()
            ->route('teacher.profile')
            ->with('status', 'profile-updated');
    }
}
