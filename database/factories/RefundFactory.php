<?php

namespace Database\Factories;

use App\Enums\RefundStatus;
use App\Models\Payment;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Refund>
 */
class RefundFactory extends Factory
{
    protected $model = Refund::class;

    public function definition(): array
    {
        $payment = Payment::factory()->refunded()->create();

        return [
            'payment_id' => $payment->id,
            'booking_id' => $payment->booking_id,
            'amount_minor' => $payment->amount_minor,
            'percent' => 100,
            'status' => RefundStatus::Processed,
            'initiated_by' => Refund::INITIATED_BY_ADMIN,
            'reason' => 'Lesson cancelled before the free cancellation window closed.',
            'gateway_refund_id' => 'rfnd_'.Str::lower(Str::random(12)),
            'refunded_at' => now(),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RefundStatus::Pending,
            'gateway_refund_id' => null,
            'refunded_at' => null,
        ]);
    }
}
