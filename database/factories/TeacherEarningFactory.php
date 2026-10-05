<?php

namespace Database\Factories;

use App\Enums\EarningStatus;
use App\Models\Booking;
use App\Models\TeacherEarning;
use App\Models\TeacherProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeacherEarning>
 */
class TeacherEarningFactory extends Factory
{
    protected $model = TeacherEarning::class;

    public function definition(): array
    {
        $booking = Booking::factory()->create();

        return [
            'teacher_profile_id' => $booking->teacher_profile_id,
            'booking_id' => $booking->id,
            'payment_id' => null,
            'payout_id' => null,
            'amount_minor' => $booking->teacher_payout_minor,
            'reversed_minor' => 0,
            'currency' => 'LKR',
            'status' => EarningStatus::Pending,
            'available_at' => $booking->ends_at,
        ];
    }

    public function forTeacher(TeacherProfile $teacher): static
    {
        return $this->state(fn (array $attributes) => ['teacher_profile_id' => $teacher->id]);
    }

    public function eligible(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EarningStatus::Eligible,
            'available_at' => now()->subMinute(),
        ]);
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => EarningStatus::Paid,
            'paid_at' => now(),
        ]);
    }
}
