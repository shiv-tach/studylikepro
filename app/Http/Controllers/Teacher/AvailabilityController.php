<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\AvailabilitySettingsRequest;
use App\Http\Requests\AvailabilitySlotRequest;
use App\Http\Requests\TimeOffRequest;
use App\Http\Requests\WeeklyAvailabilityRequest;
use App\Models\TeacherAvailabilitySlot;
use App\Models\TeacherProfile;
use App\Models\TeacherTimeOff;
use App\Services\SlotService;
use App\Services\WeeklyAvailabilityService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AvailabilityController extends Controller
{
    public function __construct(private readonly WeeklyAvailabilityService $weeks) {}

    /**
     * Onboarding step 2: set the whole week in one place, with presets.
     */
    public function setup(Request $request): View|RedirectResponse
    {
        $profile = $request->user()->teacherProfile;

        if ($gate = $this->onboardingGate($profile)) {
            return $gate;
        }

        $profile->load('availabilitySlots');

        return view('teacher.availability-setup', [
            'profile' => $profile,
        ]);
    }

    /**
     * Replace the teacher's weekly ranges with the submitted week.
     */
    public function updateWeek(WeeklyAvailabilityRequest $request): RedirectResponse
    {
        $profile = $request->user()->teacherProfile;

        if ($gate = $this->onboardingGate($profile)) {
            return $gate;
        }

        $this->weeks->replace($profile, $request->input('days', []));

        return redirect()
            ->route('teacher.verification')
            ->with('status', 'availability-saved');
    }

    /**
     * The setup screen belongs to onboarding: the profile has to be saved
     * first, and a teacher who already submitted their documents keeps using
     * the full manager instead.
     */
    private function onboardingGate(?TeacherProfile $profile): ?RedirectResponse
    {
        if (! $profile?->isComplete()) {
            return redirect()
                ->route('teacher.profile')
                ->with('status', 'complete-your-profile');
        }

        if ($profile->hasSubmittedVerification()) {
            return redirect()->route('teacher.availability.index');
        }

        return null;
    }

    /**
     * Weekly availability, time off, lesson length, and pricing summary.
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
            'profile' => $profile,
            'slotsByDay' => $profile->availabilitySlots->groupBy('day_of_week'),
            'timeOff' => $profile->timeOff,
            'preview' => $slots->upcomingByDate($profile, 7, 12),
            'commissionPercent' => platform_settings()->int('commission_percent'),
        ]);
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
