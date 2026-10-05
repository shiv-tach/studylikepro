<?php

namespace Database\Factories;

use App\Enums\ClassificationStatus;
use App\Enums\RequestStatus;
use App\Models\TutoringRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TutoringRequest>
 */
class TutoringRequestFactory extends Factory
{
    protected $model = TutoringRequest::class;

    public function definition(): array
    {
        return [
            'student_id' => User::factory()->student(),
            'description' => fake()->paragraph(),
            'classification_status' => ClassificationStatus::Completed,
            'ai_confidence' => 0.9,
            'preferred_windows' => [
                [
                    'starts_at' => now()->addDay()->setTime(18, 0)->toIso8601String(),
                    'ends_at' => now()->addDay()->setTime(20, 0)->toIso8601String(),
                ],
            ],
            'status' => RequestStatus::Open,
            'expires_at' => now()->addDays(7),
        ];
    }

    public function pendingClassification(): static
    {
        return $this->state(fn (array $attributes) => [
            'classification_status' => ClassificationStatus::Pending,
            'ai_confidence' => null,
        ]);
    }
}
