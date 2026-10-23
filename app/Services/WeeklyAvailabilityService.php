<?php

namespace App\Services;

use App\Models\TeacherAvailabilitySlot;
use App\Models\TeacherProfile;
use Illuminate\Support\Facades\DB;

/**
 * Replaces a teacher's weekly ranges in one go. The onboarding step edits the
 * whole week, so "replace" is the correct semantic: no range can survive that
 * the teacher just removed, and a half-applied week is impossible.
 *
 * Bookings and time off are untouched — removing a weekly range only stops new
 * slots from being generated.
 */
class WeeklyAvailabilityService
{
    /**
     * @param  array<int, array{value: int|string, enabled?: bool|string, ranges?: array<int, array{start_time?: ?string, end_time?: ?string}>}>  $days
     * @return int the number of ranges saved
     */
    public function replace(TeacherProfile $profile, array $days): int
    {
        return DB::transaction(function () use ($profile, $days) {
            $profile->availabilitySlots()->delete();

            $saved = 0;

            foreach ($days as $day) {
                if (! filter_var($day['enabled'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    continue;
                }

                $dayOfWeek = (int) ($day['value'] ?? -1);

                if ($dayOfWeek < 0 || $dayOfWeek > 6) {
                    continue;
                }

                foreach ($day['ranges'] ?? [] as $range) {
                    $start = $range['start_time'] ?? null;
                    $end = $range['end_time'] ?? null;

                    if (blank($start) || blank($end)) {
                        continue;
                    }

                    TeacherAvailabilitySlot::create([
                        'teacher_profile_id' => $profile->id,
                        'day_of_week' => $dayOfWeek,
                        'start_minute' => TeacherAvailabilitySlot::toMinute($start),
                        'end_minute' => TeacherAvailabilitySlot::toMinute($end),
                    ]);

                    $saved++;
                }
            }

            $profile->unsetRelation('availabilitySlots');

            return $saved;
        });
    }
}
