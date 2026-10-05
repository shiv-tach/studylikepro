<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Events\RefundProcessed;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\PlatformSettings;
use Illuminate\Support\Facades\DB;

/**
 * Refunds per the cancellation policy: a teacher cancellation is always refunded
 * in full, a student cancelling outside the free window is refunded in full, and
 * admins can override the percentage on any paid booking.
 */
class RefundService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly PlatformSettings $settings,
        private readonly EarningsService $earnings,
        private readonly PaymentService $payments,
    ) {}

    /**
     * The refund percentage the policy grants for a cancellation by this party.
     */
    public function percentForCancellation(Booking $booking, string $initiatedBy): int
    {
        return match ($initiatedBy) {
            Refund::INITIATED_BY_TEACHER => $this->settings->int('refund_teacher_percent'),
            Refund::INITIATED_BY_STUDENT => $booking->isInsideStudentCancelWindow($this->settings->int('student_cancel_window_hours'))
                ? 0
                : $this->settings->int('refund_student_percent'),
            default => $this->settings->int('refund_student_percent'),
        };
    }

    /**
     * Refund a cancelled booking according to the policy. Bookings that were
     * never paid simply have nothing to refund.
     */
    public function refundForCancellation(Booking $booking, string $initiatedBy, ?string $reason = null): ?Refund
    {
        $payment = $this->payments->capturedFor($booking);

        if ($payment === null) {
            return null;
        }

        return $this->refund($payment, $this->percentForCancellation($booking, $initiatedBy), $initiatedBy, $reason);
    }

    /**
     * Refund a percentage of a captured payment (clamped to what is left).
     */
    public function refund(
        Payment $payment,
        int $percent,
        string $initiatedBy,
        ?string $reason = null,
        ?int $amountMinor = null,
    ): ?Refund {
        $percent = max(0, min(100, $percent));

        if ($percent === 0 || ! $payment->status->isSettled() || $payment->refundableMinor() <= 0) {
            return null;
        }

        $amount = min(
            $amountMinor ?? (int) round($payment->amount_minor * $percent / 100),
            $payment->refundableMinor(),
        );

        if ($amount <= 0) {
            return null;
        }

        $remote = $this->gateway->refund($payment, $amount);
        $processed = $remote->isProcessed();

        $refund = DB::transaction(function () use ($payment, $percent, $amount, $initiatedBy, $reason, $remote, $processed) {
            $refund = $payment->refunds()->create([
                'booking_id' => $payment->booking_id,
                'amount_minor' => $amount,
                'percent' => $percent,
                'status' => $processed ? RefundStatus::Processed : RefundStatus::Pending,
                'initiated_by' => $initiatedBy,
                'reason' => $reason,
                'gateway_refund_id' => $remote->id,
                'refunded_at' => $processed ? now() : null,
                'gateway_payload' => $remote->payload,
            ]);

            $refundedTotal = (int) $payment->refunds()
                ->where('status', RefundStatus::Processed->value)
                ->sum('amount_minor');

            $payment->forceFill([
                'status' => $refundedTotal >= $payment->amount_minor
                    ? PaymentStatus::Refunded
                    : PaymentStatus::PartiallyRefunded,
                'refunded_at' => now(),
            ])->save();

            $this->earnings->reverseForRefund($payment->booking, $percent);

            return $refund;
        });

        RefundProcessed::dispatch($refund);

        return $refund;
    }
}
