<?php

namespace Database\Factories;

use App\Enums\BookingFeeDiscountType;
use App\Models\BookingFeePromotion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<BookingFeePromotion>
 */
class BookingFeePromotionFactory extends Factory
{
    protected $model = BookingFeePromotion::class;

    public function definition(): array
    {
        return [
            'name' => 'Free booking weekend',
            'discount_type' => BookingFeeDiscountType::Waive,
            'discount_value' => 0,
            'starts_at' => Carbon::now()->subHour(),
            'ends_at' => Carbon::now()->addWeek(),
            'is_active' => true,
        ];
    }

    public function percent(int $percent = 50): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $percent.'% off the booking fee',
            'discount_type' => BookingFeeDiscountType::Percent,
            'discount_value' => $percent,
        ]);
    }

    public function fixed(int $amountMinor = 5000): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => platform_settings()->formatMinor($amountMinor).' off the booking fee',
            'discount_type' => BookingFeeDiscountType::Fixed,
            'discount_value' => $amountMinor,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => Carbon::now()->subWeek(),
            'ends_at' => Carbon::now()->subHour(),
        ]);
    }

    public function upcoming(): static
    {
        return $this->state(fn (array $attributes) => [
            'starts_at' => Carbon::now()->addDay(),
            'ends_at' => Carbon::now()->addWeek(),
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
