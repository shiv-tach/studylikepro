<?php

namespace Database\Factories;

use App\Models\TeacherProfile;
use App\Models\TeacherTimeOff;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeacherTimeOff>
 */
class TeacherTimeOffFactory extends Factory
{
    protected $model = TeacherTimeOff::class;

    public function definition(): array
    {
        return [
            'teacher_profile_id' => TeacherProfile::factory(),
            'starts_on' => now()->addWeek()->toDateString(),
            'ends_on' => now()->addWeek()->addDays(2)->toDateString(),
            'reason' => fake()->randomElement(['Family function', 'Travel', 'Exam duty', null]),
        ];
    }
}
