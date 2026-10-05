<?php

namespace Database\Factories;

use App\Models\TeacherAvailabilitySlot;
use App\Models\TeacherProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeacherAvailabilitySlot>
 */
class TeacherAvailabilitySlotFactory extends Factory
{
    protected $model = TeacherAvailabilitySlot::class;

    public function definition(): array
    {
        return [
            'teacher_profile_id' => TeacherProfile::factory(),
            'day_of_week' => 1,
            'start_minute' => 18 * 60,
            'end_minute' => 21 * 60,
        ];
    }

    public function on(int $dayOfWeek, string $start, string $end): static
    {
        return $this->state(fn (array $attributes) => [
            'day_of_week' => $dayOfWeek,
            'start_minute' => TeacherAvailabilitySlot::toMinute($start),
            'end_minute' => TeacherAvailabilitySlot::toMinute($end),
        ]);
    }
}
