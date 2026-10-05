<?php

namespace Database\Factories;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        // The booking factory is only resolved when the caller did not supply a
        // booking id, so primed payments never leave a stray booking behind.
        return [
            'booking_id' => Booking::factory(),
            'student_id' => fn (array $attributes) => Booking::query()
                ->findOrFail($attributes['booking_id'])->student_id,
            'gateway' => 'fake',
            'gateway_order_id' => 'order_'.Str::lower(Str::random(12)),
            'gateway_payment_id' => 'pay_'.Str::lower(Str::random(12)),
            'amount_minor' => fn (array $attributes) => Booking::query()
                ->findOrFail($attributes['booking_id'])->price_minor,
            'currency' => fn (array $attributes) => Booking::query()
                ->findOrFail($attributes['booking_id'])->currency,
            'status' => PaymentStatus::Captured,
            'method' => 'upi',
            'captured_at' => now(),
        ];
    }

    /**
     * An order the student has not paid yet.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Created,
            'gateway_payment_id' => null,
            'method' => null,
            'captured_at' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Failed,
            'gateway_payment_id' => null,
            'captured_at' => null,
            'failed_at' => now(),
            'failure_reason' => 'Payment declined by the bank.',
        ]);
    }

    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PaymentStatus::Refunded,
            'refunded_at' => now(),
        ]);
    }
}
