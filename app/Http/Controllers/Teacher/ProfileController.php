<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Concerns\StoresAvatar;
use App\Http\Controllers\Controller;
use App\Http\Requests\TeacherOnboardingAboutRequest;
use App\Http\Requests\TeacherOnboardingPreferencesRequest;
use App\Http\Requests\TeacherProfileRequest;
use App\Services\TeacherOnboardingService;
use App\Support\TeacherOnboarding;
use App\Support\TeacherProfileConfig;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\MessageBag;
use Illuminate\View\View;

class ProfileController extends Controller
{
    use StoresAvatar;

    public function __construct(private readonly TeacherOnboardingService $onboarding) {}

    /**
     * The workspace teaching profile. Onboarding is the wizard's job, so a
     * teacher who has not finished it is sent there instead.
     */
    public function edit(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user->hasCompletedOnboarding()) {
            $user->loadMissing('onboardingDraft');

            return redirect()
                ->route(TeacherOnboarding::nextRouteName($user->teacherProfile, $user->onboardingDraft))
                ->with('status', TeacherOnboarding::nextStatus($user->teacherProfile, $user->onboardingDraft));
        }

        // The preview card shows the teacher's subjects exactly as the public
        // directory does, so they are loaded up front instead of lazily.
        $user->loadMissing(['teacherProfile.subjects', 'onboardingDraft']);

        return view('teacher.profile', [
            'profile' => $user->teacherProfile,
            'config' => TeacherProfileConfig::build(
                $user,
                $user->teacherProfile,
                $user->onboardingDraft,
                $request->session()->get('errors')?->getBag('default') ?? new MessageBag,
                $user->theme['preset'] ?? 'classic',
                'workspace',
                'all',
            ),
        ]);
    }

    /**
     * Update the whole profile (the workspace edit form).
     */
    public function update(TeacherProfileRequest $request): RedirectResponse
    {
        $user = $request->user();
        $wasIncomplete = ! $user->teacherProfile?->isComplete();

        $this->onboarding->saveWholeProfile($user, $request->safe()->except(['avatar']));
        $this->updateAvatar($request, $user);
        $this->onboarding->clearDraft($user);

        if ($wasIncomplete) {
            return redirect()
                ->route('teacher.onboarding.availability')
                ->with('status', 'profile-completed');
        }

        return redirect()
            ->route('teacher.profile')
            ->with('status', 'profile-updated');
    }

    /**
     * Wizard step 2: "About you".
     */
    public function editAbout(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->redirectIfOnboarded($request)) {
            return $redirect;
        }

        $user = $request->user();
        $user->loadMissing(['teacherProfile.subjects', 'onboardingDraft']);

        return view('teacher.onboarding.about', [
            'profile' => $user->teacherProfile,
            'config' => TeacherProfileConfig::build(
                $user,
                $user->teacherProfile,
                $user->onboardingDraft,
                $request->session()->get('errors')?->getBag('default') ?? new MessageBag,
                $user->theme['preset'] ?? 'classic',
                'onboarding',
                TeacherOnboarding::STEP_ABOUT,
            ),
        ]);
    }

    public function updateAbout(TeacherOnboardingAboutRequest $request): RedirectResponse
    {
        $user = $request->user();

        $this->onboarding->saveAbout($user, $request->validated());
        $this->onboarding->clearDraft($user, TeacherOnboarding::STEP_ABOUT);

        return redirect()
            ->route('teacher.onboarding.preferences')
            ->with('status', 'about-saved');
    }

    /**
     * Wizard step 3: "Teaching preferences".
     */
    public function editPreferences(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->redirectIfOnboarded($request)) {
            return $redirect;
        }

        $user = $request->user();
        $profile = $user->teacherProfile;

        if (! $profile?->hasCompletedAboutStep()) {
            return redirect()
                ->route('teacher.onboarding.profile')
                ->with('status', 'complete-your-profile');
        }

        $user->loadMissing(['teacherProfile.subjects', 'onboardingDraft']);

        return view('teacher.onboarding.preferences', [
            'profile' => $profile,
            'config' => TeacherProfileConfig::build(
                $user,
                $profile,
                $user->onboardingDraft,
                $request->session()->get('errors')?->getBag('default') ?? new MessageBag,
                $user->theme['preset'] ?? 'classic',
                'onboarding',
                TeacherOnboarding::STEP_PREFERENCES,
            ),
        ]);
    }

    public function updatePreferences(TeacherOnboardingPreferencesRequest $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user->teacherProfile?->hasCompletedAboutStep()) {
            return redirect()
                ->route('teacher.onboarding.profile')
                ->with('status', 'complete-your-profile');
        }

        $this->onboarding->savePreferences($user, $request->safe()->except(['avatar']));
        $this->updateAvatar($request, $user);
        $this->onboarding->clearDraft($user, TeacherOnboarding::STEP_PREFERENCES);

        return redirect()
            ->route('teacher.onboarding.availability')
            ->with('status', 'preferences-saved');
    }

    /**
     * A finished teacher edits the profile in the workspace, not in the wizard.
     */
    private function redirectIfOnboarded(Request $request): ?RedirectResponse
    {
        return $request->user()->hasCompletedOnboarding()
            ? redirect()->route('teacher.profile')
            : null;
    }
}
