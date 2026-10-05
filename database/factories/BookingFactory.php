<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    public function definition(): array
    {
        $price = 70000;
        $commission = platform_settings()->int('commission_percent');
        $fee = (int) round($price * $commission / 100);

        return [
            'student_id' => User::factory()->student(),
            'teacher_profile_id' => TeacherProfile::factory()->approved(),
            'starts_at' => now()->addDay()->setTime(18, 0),
            'ends_at' => now()->addDay()->setTime(19, 0),
            'status' => BookingStatus::Confirmed,
            'price_minor' => $price,
            'commission_percent' => $commission,
            'platform_fee_minor' => $fee,
            'teacher_payout_minor' => $price - $fee,
            'currency' => 'LKR',
        ];
    }

    public function hold(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::PendingPayment,
            'expires_at' => now()->addMinutes(platform_settings()->int('hold_ttl_minutes')),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => BookingStatus::Expired,
            'expires_at' => now()->subMinute(),
        ]);
    }
}
