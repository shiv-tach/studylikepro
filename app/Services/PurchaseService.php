<?php

namespace App\Services;

use App\Contracts\PaymentGateway;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Enums\ServiceType;
use App\Exceptions\UnsupportedCurrencyException;
use App\Models\Payment;
use App\Models\ServiceOffering;
use App\Models\ServicePurchase;
use App\Models\User;
use App\Services\Payments\EarningsService;
use Illuminate\Validation\ValidationException;

/**
 * Owns the service-purchase lifecycle: creating a priced purchase from an
 * offering, running checkout, and activating/completing/cancelling it.
 */
class PurchaseService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly BookingService $bookings,
        private readonly BookingFeeService $fees,
        private readonly PlatformSettings $settings,
        private readonly EarningsService $earnings,
    ) {}

    /**
     * Create a pending-payment purchase from an active offering.
     */
    public function create(ServiceOffering $offering, User $student): ServicePurchase
    {
        if (! $offering->is_active) {
            throw ValidationException::withMessages([
                'offering' => __('This service is no longer available.'),
            ]);
        }

        // A percentage fee is priced off what the service costs the student.
        $fee = $this->fees->quote(priceMinor: (int) $offering->price_minor);

        $purchase = ServicePurchase::query()->create([
            'student_id' => $student->id,
            'teacher_profile_id' => $offering->teacher_profile_id,
            'offering_id' => $offering->id,
            'service_type' => $offering->service_type,
            'subject_id' => $offering->subject_id,
            'lesson_id' => $offering->lesson_id,
            'status' => PurchaseStatus::PendingPayment,
            'price_minor' => $offering->price_minor,
            'currency' => (string) config('studylikepro.currency'),
            'allocation_minutes' => $offering->allocation_minutes,
            'max_sessions' => $offering->max_sessions,
            'turnaround_hours' => $offering->turnaround_hours,
            ...$this->bookings->feeBreakdown($offering->price_minor),
            'booking_fee_minor' => $fee['booking_fee_minor'],
            'booking_fee_discount_minor' => $fee['discount_minor'],
            'booking_fee_promotion_id' => $fee['promotion']?->id,
            'expires_at' => now()->addMinutes($this->settings->int('hold_ttl_minutes')),
        ]);

        return $purchase;
    }

    /**
     * Open (or reuse) a checkout attempt for a purchase still awaiting payment.
     */
    public function startCheckout(ServicePurchase $purchase): Payment
    {
        if ($purchase->status !== PurchaseStatus::PendingPayment) {
            throw ValidationException::withMessages([
                'status' => __('This purchase can no longer be paid for.'),
            ]);
        }

        if ($purchase->expires_at !== null && $purchase->expires_at->isPast()) {
            $this->expire($purchase);

            throw ValidationException::withMessages([
                'status' => __('Your hold has expired — start again to book this service.'),
            ]);
        }

        // A manual slip is with our team; opening a second checkout would
        // invite a double payment.
        if ($purchase->payments()->where('status', PaymentStatus::Submitted->value)->exists()) {
            throw ValidationException::withMessages([
                'status' => __('Your payment slip is awaiting verification — we will confirm it shortly.'),
            ]);
        }

        $existing = $purchase->payments()
            ->where('status', PaymentStatus::Created->value)
            ->where('amount_minor', $purchase->totalMinor())
            ->latest()
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        // The card checkout is "coming soon", so the provider order is only
        // opened when the gateway can price this marketplace's currency.
        $order = null;

        try {
            $order = $this->gateway->createOrder($purchase, $purchase->totalMinor(), $purchase->getCurrency());
        } catch (UnsupportedCurrencyException) {
            // Manual settlement does not need a provider order.
        }

        return Payment::query()->create([
            'service_purchase_id' => $purchase->id,
            'student_id' => $purchase->student_id,
            'gateway' => $this->gateway->name(),
            'gateway_order_id' => $order?->id,
            'amount_minor' => $order?->amountMinor ?? $purchase->totalMinor(),
            'currency' => $order?->currency ?? $purchase->getCurrency(),
            'status' => PaymentStatus::Created,
            'gateway_payload' => $order?->payload,
        ]);
    }

    /**
     * Payment captured: make the purchase consumable. Idempotent.
     */
    public function activate(ServicePurchase $purchase): ServicePurchase
    {
        if ($purchase->status !== PurchaseStatus::PendingPayment) {
            return $purchase;
        }

        $purchase->forceFill([
            'status' => PurchaseStatus::Active,
            'paid_at' => now(),
            // Lesson plans must be consumed before this deadline; other
            // services carry no fixed expiry.
            'expires_at' => $purchase->isLessonPlan()
                ? now()->addDays($this->settings->int('plan_expiry_days'))
                : null,
        ])->save();

        return $purchase;
    }

    public function complete(ServicePurchase $purchase): ServicePurchase
    {
        if ($purchase->status !== PurchaseStatus::Active) {
            return $purchase;
        }

        $purchase->forceFill([
            'status' => PurchaseStatus::Completed,
            'completed_at' => now(),
            'used_minutes' => $purchase->usedSessionMinutes(),
        ])->save();

        $this->earnings->markEligibleForPurchase($purchase);

        return $purchase;
    }

    public function cancel(ServicePurchase $purchase, string $by, ?string $reason = null): ServicePurchase
    {
        if ($purchase->status->isFinal()) {
            return $purchase;
        }

        $purchase->forceFill([
            'status' => PurchaseStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by' => $by,
            'cancellation_reason' => $reason,
        ])->save();

        return $purchase;
    }

    /**
     * A pending-payment purchase whose hold ran out.
     */
    public function expire(ServicePurchase $purchase): ServicePurchase
    {
        if ($purchase->status !== PurchaseStatus::PendingPayment) {
            return $purchase;
        }

        $purchase->forceFill([
            'status' => PurchaseStatus::Expired,
        ])->save();

        return $purchase;
    }

    /**
     * An active purchase whose deadline passed (lesson plans only in V1).
     * Refunds are applied by the caller; this closes the purchase and releases
     * any payout for the delivered share.
     */
    public function markExpired(ServicePurchase $purchase): ServicePurchase
    {
        if ($purchase->status !== PurchaseStatus::Active) {
            return $purchase;
        }

        $purchase->forceFill([
            'status' => PurchaseStatus::Expired,
            'used_minutes' => $purchase->usedSessionMinutes(),
        ])->save();

        $this->earnings->markEligibleForPurchase($purchase);

        return $purchase;
    }

    /**
     * Reconcile stored allocation bookkeeping from the purchase sessions and
     * complete the purchase when the service is fully delivered: lesson plans
     * when the allocation is consumed, single-session services when a session
     * completes.
     */
    public function syncDelivery(ServicePurchase $purchase): ServicePurchase
    {
        $purchase->forceFill([
            'used_minutes' => $purchase->usedSessionMinutes(),
        ])->save();

        if (! $purchase->isActive()) {
            return $purchase->refresh();
        }

        if ($purchase->isLessonPlan()) {
            if ($purchase->remainingMinutes() === 0 && ! $purchase->hasLiveSessions()) {
                $this->complete($purchase);
            }

            return $purchase->refresh();
        }

        if ($purchase->sessions()
            ->where('status', BookingStatus::Completed->value)
            ->exists()) {
            $this->complete($purchase);
        }

        return $purchase->refresh();
    }

    /**
     * Payments that already have everything they need to be a receipt.
     */
    public function capturedFor(ServicePurchase $purchase): ?Payment
    {
        return $purchase->payments()
            ->whereIn('status', [
                PaymentStatus::Captured->value,
                PaymentStatus::PartiallyRefunded->value,
                PaymentStatus::Refunded->value,
            ])
            ->latest()
            ->first();
    }

    /**
     * Validate an offering's type-specific configuration before it is saved.
     * Used by both the teacher console and the admin console.
     */
    public function validateOfferingAttributes(ServiceOffering $offering): void
    {
        $type = $offering->service_type;

        if ($type === ServiceType::Lesson) {
            $allocation = (int) ($offering->allocation_minutes ?? 0);

            if ($allocation < 30) {
                throw ValidationException::withMessages([
                    'allocation_minutes' => __('The time allocation must be at least 30 minutes.'),
                ]);
            }

            if ($offering->default_session_minutes !== null
                && ($offering->default_session_minutes < 30 || $offering->default_session_minutes > $allocation)) {
                throw ValidationException::withMessages([
                    'default_session_minutes' => __('The default session length must sit between 30 minutes and the total allocation.'),
                ]);
            }

            if ($offering->max_sessions !== null
                && $offering->max_sessions > $this->settings->int('max_sessions_per_plan')) {
                throw ValidationException::withMessages([
                    'max_sessions' => __('A plan cannot have more than :max sessions.', [
                        'max' => $this->settings->int('max_sessions_per_plan'),
                    ]),
                ]);
            }
        }

        if ($type === ServiceType::PaperMarking) {
            if ($offering->turnaround_hours === null || $offering->turnaround_hours < 1) {
                throw ValidationException::withMessages([
                    'turnaround_hours' => __('Set a turnaround time of at least one hour.'),
                ]);
            }

            if ($offering->max_pages !== null && $offering->max_pages < 1) {
                throw ValidationException::withMessages([
                    'max_pages' => __('The page limit must be at least one page.'),
                ]);
            }
        }
    }
}
