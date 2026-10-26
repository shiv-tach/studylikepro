<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\AvailabilityPricingRequest;
use App\Http\Requests\AvailabilitySettingsRequest;
use App\Http\Requests\AvailabilitySlotRequest;
use App\Http\Requests\TimeOffRequest;
use App\Http\Requests\WeeklyAvailabilityRequest;
use App\Models\TeacherAvailabilitySlot;
use App\Models\TeacherProfile;
use App\Models\TeacherTimeOff;
use App\Services\SlotService;
use App\Services\TeacherOnboardingService;
use App\Services\WeeklyAvailabilityService;
use App\Support\AvailabilityManagerConfig;
use App\Support\AvailabilitySetupConfig;
use App\Support\TeacherOnboarding;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AvailabilityController extends Controller
{
    public function __construct(
        private readonly WeeklyAvailabilityService $weeks,
        private readonly TeacherOnboardingService $onboarding,
    ) {}

    /**
     * Wizard step 4: set the whole week in one place, with presets.
     */
    public function setup(Request $request, string $mode = 'legacy'): View|RedirectResponse
    {
        $user = $request->user();
        $profile = $user->teacherProfile;

        // The old URL only forwards: the wizard owns the setup screen now.
        if ($mode !== 'onboarding') {
            return $profile?->hasSubmittedVerification()
                ? redirect()->route('teacher.availability.index')
                : redirect()->route('teacher.onboarding.availability');
        }

        if ($gate = $this->onboardingGate($profile)) {
            return $gate;
        }

        $profile->load('availabilitySlots');
        $user->loadMissing('onboardingDraft');

        return view('teacher.onboarding.availability', [
            'profile' => $profile,
            'config' => AvailabilitySetupConfig::build(
                $profile,
                $request->session()->get('errors')?->getBag('default') ?? new \Illuminate\Support\MessageBag,
                $user->theme['preset'] ?? 'classic',
                $user->onboardingDraft,
            ),
        ]);
    }

    /**
     * Replace the teacher's weekly ranges with the submitted week.
     */
    public function updateWeek(WeeklyAvailabilityRequest $request): RedirectResponse
    {
        $user = $request->user();
        $profile = $user->teacherProfile;

        if ($gate = $this->onboardingGate($profile)) {
            return $gate;
        }

        $this->weeks->replace($profile, $request->input('days', []));
        $this->onboarding->clearDraft($user, TeacherOnboarding::STEP_AVAILABILITY);

        return redirect()
            ->route('teacher.onboarding.verification')
            ->with('status', 'availability-saved');
    }

    /**
     * The setup screen belongs to onboarding: both profile steps have to be
     * saved first, and a teacher who already submitted their documents keeps
     * using the full manager instead.
     */
    private function onboardingGate(?TeacherProfile $profile): ?RedirectResponse
    {
        if (! $profile?->hasCompletedAboutStep()) {
            return redirect()
                ->route('teacher.onboarding.profile')
                ->with('status', 'complete-your-profile');
        }

        if (! $profile->hasCompletedPreferencesStep()) {
            return redirect()
                ->route('teacher.onboarding.preferences')
                ->with('status', 'complete-your-preferences');
        }

        if ($profile->hasSubmittedVerification()) {
            return redirect()->route('teacher.availability.index');
        }

        return null;
    }

    /**
     * Weekly availability, time off, lesson length and the default price.
     */
    public function index(Request $request, SlotService $slots): View
    {
        $profile = $request->user()->teacherProfile;
        $profile->load([
            'availabilitySlots',
            'timeOff',
            'subjects.educationLevel.grades' => fn ($query) => $query->where('is_active', true)->ordered(),
        ]);

        return view('teacher.availability', [
            'config' => AvailabilityManagerConfig::build(
                $profile,
                $request->user()->theme['preset'] ?? 'classic',
                $slots->upcomingByDate($profile, 7, 12),
                platform_settings()->int('commission_percent'),
            ),
        ]);
    }

    /**
     * Replace the whole week from the manager page. The onboarding step posts to
     * its own gate-protected route; this one only needs the teacher role.
     */
    public function updateRanges(WeeklyAvailabilityRequest $request): RedirectResponse
    {
        $this->weeks->replace($request->user()->teacherProfile, $request->input('days', []));

        return redirect()
            ->route('teacher.availability.index')
            ->with('status', 'availability-week-saved');
    }

    /**
     * The default hourly rate every booking starts from. Subject and per-grade
     * overrides live on /teacher/subjects and are untouched here.
     */
    public function updatePricing(AvailabilityPricingRequest $request): RedirectResponse
    {
        $request->user()->teacherProfile->update([
            'hourly_rate_minor' => (int) round(((float) $request->validated('hourly_rate')) * 100),
        ]);

        return redirect()
            ->route('teacher.availability.index')
            ->with('status', 'availability-pricing-updated');
    }

    public function storeSlot(AvailabilitySlotRequest $request): RedirectResponse
    {
        $profile = $request->user()->teacherProfile;

        $profile->availabilitySlots()->create($request->slotAttributes($profile->id));

        return redirect()
            ->route('teacher.availability.index')
            ->with('status', 'availability-slot-added');
    }

    public function destroySlot(Request $request, TeacherAvailabilitySlot $slot): RedirectResponse
    {
        abort_unless($slot->teacher_profile_id === $request->user()->teacherProfile->id, 403);

        $slot->delete();

        return redirect()
            ->route('teacher.availability.index')
            ->with('status', 'availability-slot-removed');
    }

    public function storeTimeOff(TimeOffRequest $request): RedirectResponse
    {
        $request->user()->teacherProfile->timeOff()->create($request->validated());

        return redirect()
            ->route('teacher.availability.index')
            ->with('status', 'time-off-added');
    }

    public function destroyTimeOff(Request $request, TeacherTimeOff $timeOff): RedirectResponse
    {
        abort_unless($timeOff->teacher_profile_id === $request->user()->teacherProfile->id, 403);

        $timeOff->delete();

        return redirect()
            ->route('teacher.availability.index')
            ->with('status', 'time-off-removed');
    }

    public function updateSettings(AvailabilitySettingsRequest $request): RedirectResponse
    {
        $request->user()->teacherProfile->update([
            'lesson_duration_minutes' => (int) $request->validated('lesson_duration_minutes'),
        ]);

        return redirect()
            ->route('teacher.availability.index')
            ->with('status', 'availability-settings-updated');
    }
}
