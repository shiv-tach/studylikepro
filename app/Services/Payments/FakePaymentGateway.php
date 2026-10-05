<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Models\Booking;
use App\Models\Payment;
use App\Support\Payments\GatewayEvent;
use App\Support\Payments\GatewayOrder;
use App\Support\Payments\GatewayPayment;
use App\Support\Payments\GatewayRefund;
use App\Support\Payments\WebhookSigner;
use Illuminate\Support\Str;

/**
 * Offline gateway used for local development and tests. It speaks the Razorpay
 * wire shape (orders, signed webhooks, refunds) so the surrounding application
 * code is identical to production, but nothing leaves the machine.
 *
 * Tests can prime results with `fakePayment()` / `fakeRefund()`.
 */
class FakePaymentGateway implements PaymentGateway
{
    /** @var array<string, array{status: string, amount_minor: int, currency: string, method: ?string}> */
    private static array $payments = [];

    /** @var array<string, array{status: string, amount_minor: int}> */
    private static array $refunds = [];

    public function name(): string
    {
        return 'fake';
    }

    /**
     * Prime what `fetchPayment()` should return for a payment id.
     */
    public static function fakePayment(
        string $paymentId,
        string $status = 'captured',
        int $amountMinor = 70000,
        ?string $currency = null,
        ?string $method = 'upi',
    ): void {
        self::$payments[$paymentId] = [
            'status' => $status,
            'amount_minor' => $amountMinor,
            'currency' => $currency ?? (string) config('studylikepro.currency'),
            'method' => $method,
        ];
    }

    /**
     * @param  array{status: string, amount_minor: int}  $refund
     */
    public static function fakeRefund(string $refundId, array $refund): void
    {
        self::$refunds[$refundId] = $refund;
    }

    public static function reset(): void
    {
        self::$payments = [];
        self::$refunds = [];
    }

    public function createOrder(Booking $booking, int $amountMinor, string $currency): GatewayOrder
    {
        return new GatewayOrder(
            id: 'order_fake_'.Str::lower(Str::random(14)),
            amountMinor: $amountMinor,
            currency: $currency,
            payload: ['gateway' => 'fake', 'booking_id' => $booking->id],
        );
    }

    public function fetchPayment(string $paymentId): GatewayPayment
    {
        $stored = self::$payments[$paymentId] ?? [
            'status' => 'created',
            'amount_minor' => 0,
            'currency' => (string) config('studylikepro.currency'),
            'method' => null,
        ];

        return new GatewayPayment(
            id: $paymentId,
            status: $stored['status'],
            amountMinor: $stored['amount_minor'],
            currency: $stored['currency'],
            method: $stored['method'],
            payload: ['gateway' => 'fake'],
        );
    }

    public function refund(Payment $payment, int $amountMinor): GatewayRefund
    {
        $id = 'rfnd_fake_'.Str::lower(Str::random(14));

        return new GatewayRefund(
            id: $id,
            status: self::$refunds[$id]['status'] ?? 'processed',
            amountMinor: self::$refunds[$id]['amount_minor'] ?? $amountMinor,
            payload: ['gateway' => 'fake'],
        );
    }

    public function verifyWebhookSignature(string $payload, ?string $signature): bool
    {
        return WebhookSigner::verify($payload, $signature, $this->webhookSecret());
    }

    public function parseWebhook(string $payload, array $headers = []): GatewayEvent
    {
        return GatewayEvent::fromRazorpayBody(json_decode($payload, true) ?: [], $headers['x-webhook-id'] ?? null);
    }

    public function checkoutPayload(Payment $payment): array
    {
        return [
            'mode' => 'fake',
            'order_id' => $payment->gateway_order_id,
            'amount' => $payment->amount_minor,
            'currency' => $payment->currency,
        ];
    }

    /**
     * Sign a payload the way the provider would, so the local checkout page can
     * push a realistic, signature-verified webhook through the real code path.
     */
    public function signPayload(string $payload): string
    {
        return WebhookSigner::sign($payload, $this->webhookSecret());
    }

    /**
     * The capture webhook body the local "pay" button submits.
     *
     * @return array{0: array<string, mixed>, 1: string} body and signature
     */
    public function localCapturePayload(Payment $payment): array
    {
        return $this->localPayload($payment, 'payment.captured', 'captured');
    }

    /**
     * The failure webhook body the local "declined" button submits.
     *
     * @return array{0: array<string, mixed>, 1: string}
     */
    public function localFailurePayload(Payment $payment): array
    {
        return $this->localPayload($payment, 'payment.failed', 'failed');
    }

    /**
     * @return array{0: array<string, mixed>, 1: string}
     */
    private function localPayload(Payment $payment, string $event, string $status): array
    {
        $body = [
            'event' => $event,
            'payload' => [
                'payment' => [
                    'entity' => [
                        'id' => 'pay_fake_'.$payment->id,
                        'order_id' => $payment->gateway_order_id,
                        'amount' => $payment->amount_minor,
                        'currency' => $payment->currency,
                        'status' => $status,
                        'method' => 'upi',
                        'error_description' => $status === 'failed' ? 'Payment was declined in the demo gateway.' : null,
                    ],
                ],
            ],
        ];

        $json = (string) json_encode($body);

        return [$body, $this->signPayload($json)];
    }

    private function webhookSecret(): string
    {
        return (string) config('studylikepro.payments.fake_webhook_secret');
    }
}
