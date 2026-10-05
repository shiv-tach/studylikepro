<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Review;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'student_id' => User::factory()->student(),
            'teacher_profile_id' => TeacherProfile::factory()->approved(),
            'rating' => fake()->numberBetween(4, 5),
            'comment' => fake()->sentence(12),
        ];
    }

    public function rating(int $rating): static
    {
        return $this->state(fn () => ['rating' => $rating]);
    }

    public function hidden(?User $by = null): static
    {
        return $this->state(fn () => [
            'hidden_at' => now(),
            'hidden_by' => $by?->id,
        ]);
    }

    public function flagged(?User $by = null, string $reason = 'unfair'): static
    {
        return $this->state(fn () => [
            'flagged_at' => now(),
            'flagged_by' => $by?->id,
            'flag_reason' => $reason,
        ]);
    }
}
