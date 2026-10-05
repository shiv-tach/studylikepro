<?php

namespace Database\Factories;

use App\Enums\DisputeStatus;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Dispute>
 */
class DisputeFactory extends Factory
{
    protected $model = Dispute::class;

    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'raised_by' => User::factory()->student(),
            'reason' => Dispute::REASON_NO_SHOW,
            'details' => fake()->sentence(),
            'status' => DisputeStatus::Open,
        ];
    }
}
