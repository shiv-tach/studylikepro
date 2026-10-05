<?php

namespace Database\Factories;

use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Models\TeacherProfile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payout>
 */
class PayoutFactory extends Factory
{
    protected $model = Payout::class;

    public function definition(): array
    {
        return [
            'teacher_profile_id' => TeacherProfile::factory()->approved(),
            'reference' => 'PAY-'.now()->format('Ym').'-'.Str::upper(Str::random(5)),
            'amount_minor' => 50000,
            'lessons_count' => 1,
            'currency' => 'LKR',
            'status' => PayoutStatus::Pending,
            'method' => 'bank_transfer',
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PayoutStatus::Paid,
            'paid_at' => now(),
        ]);
    }
}
