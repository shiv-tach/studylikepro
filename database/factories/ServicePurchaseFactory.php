<?php

namespace Database\Factories;

use App\Enums\PurchaseStatus;
use App\Enums\ServiceType;
use App\Models\ServiceOffering;
use App\Models\ServicePurchase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServicePurchase>
 */
class ServicePurchaseFactory extends Factory
{
    protected $model = ServicePurchase::class;

    public function definition(): array
    {
        $price = fake()->numberBetween(1000, 20000);
        $commission = (int) config('studylikepro.commission_percent', 15);
        $fee = (int) round($price * $commission / 100);

        $offering = ServiceOffering::factory()->create();

        return [
            'student_id' => User::factory()->student(),
            'teacher_profile_id' => $offering->teacher_profile_id,
            'offering_id' => $offering->id,
            'service_type' => $offering->service_type,
            'subject_id' => $offering->subject_id,
            'lesson_id' => $offering->lesson_id,
            'status' => PurchaseStatus::Active,
            'price_minor' => $price,
            'commission_percent' => $commission,
            'platform_fee_minor' => $fee,
            'teacher_payout_minor' => $price - $fee,
            'booking_fee_minor' => (int) config('studylikepro.booking_fee_minor', 10000),
            'booking_fee_discount_minor' => 0,
            'currency' => 'LKR',
            'allocation_minutes' => $offering->allocation_minutes,
            'used_minutes' => 0,
            'max_sessions' => $offering->max_sessions,
            'turnaround_hours' => $offering->turnaround_hours,
            'paid_at' => now(),
            'expires_at' => now()->addDays((int) config('studylikepro.services.plan_expiry_days', 90)),
        ];
    }

    public function forOffering(ServiceOffering $offering): static
    {
        return $this->state(fn (array $attributes) => [
            'teacher_profile_id' => $offering->teacher_profile_id,
            'offering_id' => $offering->id,
            'service_type' => $offering->service_type,
            'subject_id' => $offering->subject_id,
            'lesson_id' => $offering->lesson_id,
            'allocation_minutes' => $offering->allocation_minutes,
            'max_sessions' => $offering->max_sessions,
            'turnaround_hours' => $offering->turnaround_hours,
        ]);
    }

    public function forType(ServiceType $type): static
    {
        return $this->forOffering(
            ServiceOffering::factory()->forType($type)->create()
        );
    }

    public function pendingPayment(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PurchaseStatus::PendingPayment,
            'paid_at' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PurchaseStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}
