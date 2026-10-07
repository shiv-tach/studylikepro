<?php

namespace Database\Factories;

use App\Models\Grade;
use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StudentProfile>
 */
class StudentProfileFactory extends Factory
{
    protected $model = StudentProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'grade_id' => Grade::factory(),
            'timezone' => 'Asia/Colombo',
            'learning_goals' => fake()->optional()->sentence(),
            'guardian_name' => null,
            'guardian_phone' => null,
            'completed_at' => now(),
        ];
    }

    /**
     * A profile that has not finished onboarding yet.
     */
    public function incomplete(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at' => null,
        ]);
    }
}
