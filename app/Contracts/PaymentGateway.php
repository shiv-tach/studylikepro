<?php

namespace App\Contracts;

use App\Models\Booking;
use App\Models\Payment;
use App\Support\Payments\GatewayEvent;
use App\Support\Payments\GatewayOrder;
use App\Support\Payments\GatewayPayment;
use App\Support\Payments\GatewayRefund;

interface PaymentGateway
{
    /**
     * Short identifier stored on payments and webhook events.
     */
    public function name(): string;

    /**
     * Register an order with the provider for the amount the student owes.
     */
    public function createOrder(Booking $booking, int $amountMinor, string $currency): GatewayOrder;

    /**
     * Read the provider's current view of a payment (used on return and by reconcile).
     */
    public function fetchPayment(string $paymentId): GatewayPayment;

    /**
     * Refund all or part of a captured payment.
     */
    public function refund(Payment $payment, int $amountMinor): GatewayRefund;

    public function verifyWebhookSignature(string $payload, ?string $signature): bool;

    /**
     * @param  array<string, string>  $headers
     */
    public function parseWebhook(string $payload, array $headers = []): GatewayEvent;

    /**
     * Data the checkout page needs to hand over to the provider widget.
     *
     * @return array<string, mixed>
     */
    public function checkoutPayload(Payment $payment): array;
}
