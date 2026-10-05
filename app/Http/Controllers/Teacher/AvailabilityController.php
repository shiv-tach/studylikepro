<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\AvailabilitySettingsRequest;
use App\Http\Requests\AvailabilitySlotRequest;
use App\Http\Requests\TimeOffRequest;
use App\Models\TeacherAvailabilitySlot;
use App\Models\TeacherTimeOff;
use App\Services\SlotService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AvailabilityController extends Controller
{
    /**
     * Weekly availability, time off, lesson length, and pricing summary.
     */
    public function index(Request $request, SlotService $slots): View
    {
        $profile = $request->user()->teacherProfile;
        $profile->load(['availabilitySlots', 'timeOff', 'subjects']);

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
