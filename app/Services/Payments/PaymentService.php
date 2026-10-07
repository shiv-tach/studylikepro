<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Events\PaymentCaptured;
use App\Events\PaymentFailed;
use App\Models\Booking;
use App\Models\Payment;
use App\Services\BookingTransitionService;
use App\Support\Payments\GatewayEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns the payment lifecycle for a booking: opening a provider order, applying
 * the provider's answer (webhook, return URL or reconcile) and keeping the
 * booking, the payment row and the earnings ledger in step.
 */
class PaymentService
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly BookingTransitionService $transitions,
        private readonly EarningsService $earnings,
    ) {}

    /**
     * Open (or reuse) a checkout attempt for a booking that is still awaiting payment.
     *
     * The order covers the lesson price plus the booking-fee snapshot taken when
     * the slot was reserved — a hold is always charged what it quoted.
     */
    public function startCheckout(Booking $booking): Payment
    {
        if ($booking->status === BookingStatus::Confirmed) {
            throw ValidationException::withMessages([
                'status' => __('This lesson is already paid for.'),
            ]);
        }

        if ($booking->status !== BookingStatus::PendingPayment) {
            throw ValidationException::withMessages([
                'status' => __('This booking can no longer be paid for.'),
            ]);
        }

        if ($booking->expires_at !== null && $booking->expires_at->isPast()) {
            $this->transitions->expire($booking);

            throw ValidationException::withMessages([
                'status' => __('Your hold has expired — pick another slot and try again.'),
            ]);
        }

        $existing = $booking->payments()
            ->where('status', PaymentStatus::Created->value)
            ->where('amount_minor', $booking->totalMinor())
            ->latest()
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        $order = $this->gateway->createOrder($booking, $booking->totalMinor(), $booking->currency);

        return Payment::query()->create([
            'booking_id' => $booking->id,
            'student_id' => $booking->student_id,
            'gateway' => $this->gateway->name(),
            'gateway_order_id' => $order->id,
            'amount_minor' => $order->amountMinor,
            'currency' => $order->currency,
            'status' => PaymentStatus::Created,
            'gateway_payload' => $order->payload,
        ]);
    }

    /**
     * Apply a provider webhook.
     */
    public function handleWebhook(GatewayEvent $event): string
    {
        $payment = $this->locate($event);

        if ($payment === null) {
            return 'ignored';
        }

        if ($event->isCapture()) {
            $this->capture(
                $payment,
                $event->paymentId ?? (string) $payment->gateway_payment_id,
                $event->method,
                $event->amountMinor,
                $event->payload,
            );

            return 'processed';
        }

        if ($event->isFailure()) {
            $this->fail($payment, $event->failureReason ?? 'Payment failed.');

            return 'processed';
        }

        return 'ignored';
    }

    /**
     * Ask the provider what happened to an order (return URL and reconcile).
     */
    public function syncFromGateway(Payment $payment): Payment
    {
        if ($payment->gateway_payment_id === null && ! $payment->status->isSettled()) {
            // Nothing the provider can tell us until a payment attempt exists.
            return $payment;
        }

        $remote = $this->gateway->fetchPayment((string) $payment->gateway_payment_id);

        if ($remote->isCaptured()) {
            $this->capture($payment, $remote->id, $remote->method, $remote->amountMinor, $remote->payload);
        } elseif ($remote->hasFailed()) {
            $this->fail($payment, $remote->failureReason ?? 'Payment failed.');
        }

        return $payment->refresh();
    }

    /**
     * Mark the payment captured, confirm the booking and open the earning row.
     */
    public function capture(
        Payment $payment,
        string $gatewayPaymentId,
        ?string $method = null,
        ?int $amountMinor = null,
        array $payload = [],
    ): Payment {
        if ($payment->status === PaymentStatus::Captured) {
            return $payment;
        }

        DB::transaction(function () use ($payment, $gatewayPaymentId, $method, $amountMinor, $payload) {
            $payment->forceFill([
                'status' => PaymentStatus::Captured,
                'gateway_payment_id' => $gatewayPaymentId,
                'method' => $method,
                'amount_minor' => $amountMinor ?: $payment->amount_minor,
                'captured_at' => now(),
                'failure_reason' => null,
                'gateway_payload' => $payload ?: $payment->gateway_payload,
            ])->save();

            $booking = $payment->booking;

            // Confirming is idempotent, so a retried webhook is harmless.
            $this->transitions->confirm($booking);

            $this->earnings->recordForBooking($booking->refresh(), $payment);
        });

        PaymentCaptured::dispatch($payment->refresh());

        return $payment;
    }

    public function fail(Payment $payment, string $reason): Payment
    {
        if ($payment->status->isSettled()) {
            return $payment;
        }

        $payment->forceFill([
            'status' => PaymentStatus::Failed,
            'failed_at' => now(),
            'failure_reason' => $reason,
        ])->save();

        PaymentFailed::dispatch($payment, $reason);

        return $payment;
    }

    /**
     * Payments that already have everything they need to be a receipt.
     */
    public function capturedFor(Booking $booking): ?Payment
    {
        return $booking->payments()
            ->whereIn('status', [
                PaymentStatus::Captured->value,
                PaymentStatus::PartiallyRefunded->value,
                PaymentStatus::Refunded->value,
            ])
            ->latest()
            ->first();
    }

    private function locate(GatewayEvent $event): ?Payment
    {
        $query = Payment::query();

        if ($event->paymentId !== null && $event->orderId !== null) {
            $query->where(function ($query) use ($event) {
                $query->where('gateway_payment_id', $event->paymentId)
                    ->orWhere('gateway_order_id', $event->orderId);
            });
        } elseif ($event->paymentId !== null) {
            $query->where('gateway_payment_id', $event->paymentId);
        } elseif ($event->orderId !== null) {
            $query->where('gateway_order_id', $event->orderId);
        } else {
            return null;
        }

        return $query->latest()->first();
    }
}
