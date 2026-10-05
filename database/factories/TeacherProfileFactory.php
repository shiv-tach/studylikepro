<?php

namespace Database\Factories;

use App\Enums\VerificationStatus;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeacherProfile>
 */
class TeacherProfileFactory extends Factory
{
    protected $model = TeacherProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->teacher(),
            'headline' => fake()->jobTitle().' tutor',
            'bio' => fake()->paragraph(),
            'experience_years' => fake()->numberBetween(1, 15),
            'education' => 'M.Sc.',
            'languages' => ['English'],
            'timezone' => 'Asia/Kolkata',
            'hourly_rate_minor' => 50000,
            'verification_status' => VerificationStatus::Draft,
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

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at' => now(),
            'verification_status' => VerificationStatus::Pending,
            'submitted_at' => now(),
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at' => now(),
            'verification_status' => VerificationStatus::Approved,
            'submitted_at' => now()->subDay(),
            'verified_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'completed_at' => now(),
            'verification_status' => VerificationStatus::Rejected,
            'submitted_at' => now()->subDay(),
            'verification_notes' => 'Please upload a clearer photo of your government ID.',
        ]);
    }
}
