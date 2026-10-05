<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Exceptions\UnsupportedCurrencyException;
use App\Models\Booking;
use App\Models\Payment;
use App\Support\Payments\GatewayEvent;
use App\Support\Payments\GatewayOrder;
use App\Support\Payments\GatewayPayment;
use App\Support\Payments\GatewayRefund;
use App\Support\Payments\WebhookSigner;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Razorpay REST integration (orders, payments, refunds) with webhook signature
 * verification. No SDK: the three endpoints we need are trivial over HTTP.
 */
class RazorpayGateway implements PaymentGateway
{
    /**
     * Razorpay settles in Indian rupees only.
     */
    public const SUPPORTED_CURRENCY = 'INR';

    public function name(): string
    {
        return 'razorpay';
    }

    public function createOrder(Booking $booking, int $amountMinor, string $currency): GatewayOrder
    {
        $this->assertCurrencySupported($currency);

        $response = $this->client()->post($this->url('/orders'), [
            'amount' => $amountMinor,
            'currency' => $currency,
            'receipt' => 'booking_'.$booking->id,
            'notes' => [
                'booking_id' => (string) $booking->id,
                'teacher_profile_id' => (string) $booking->teacher_profile_id,
            ],
        ]);

        $response->throw();

        return new GatewayOrder(
            id: (string) $response->json('id'),
            amountMinor: (int) $response->json('amount'),
            currency: (string) $response->json('currency'),
            payload: (array) $response->json(),
        );
    }

    public function fetchPayment(string $paymentId): GatewayPayment
    {
        $response = $this->client()->get($this->url('/payments/'.$paymentId));

        $response->throw();

        return new GatewayPayment(
            id: (string) ($response->json('id') ?? $paymentId),
            status: (string) $response->json('status'),
            amountMinor: (int) $response->json('amount'),
            currency: (string) $response->json('currency'),
            method: $response->json('method'),
            failureReason: $response->json('error_description'),
            payload: (array) $response->json(),
        );
    }

    public function refund(Payment $payment, int $amountMinor): GatewayRefund
    {
        $response = $this->client()->post($this->url('/payments/'.$payment->gateway_payment_id.'/refund'), [
            'amount' => $amountMinor,
            'speed' => 'normal',
            'notes' => ['booking_id' => (string) $payment->booking_id],
        ]);

        $response->throw();

        return new GatewayRefund(
            id: (string) $response->json('id'),
            status: (string) $response->json('status'),
            amountMinor: (int) $response->json('amount'),
            payload: (array) $response->json(),
        );
    }

    public function verifyWebhookSignature(string $payload, ?string $signature): bool
    {
        return WebhookSigner::verify($payload, $signature, (string) config('services.razorpay.webhook_secret'));
    }

    public function parseWebhook(string $payload, array $headers = []): GatewayEvent
    {
        return GatewayEvent::fromRazorpayBody(
            json_decode($payload, true) ?: [],
            $headers['x-razorpay-event-id'] ?? null,
        );
    }

    public function checkoutPayload(Payment $payment): array
    {
        $this->assertCurrencySupported($payment->currency);

        return [
            'mode' => 'razorpay',
            'key' => (string) config('services.razorpay.key'),
            'order_id' => $payment->gateway_order_id,
            'amount' => $payment->amount_minor,
            'currency' => $payment->currency,
            'name' => config('app.name'),
            'description' => 'Lesson #'.str_pad((string) $payment->booking_id, 6, '0', STR_PAD_LEFT),
            'callback_url' => route('payments.return', $payment),
        ];
    }

    private function assertCurrencySupported(string $currency): void
    {
        if (strtoupper($currency) === self::SUPPORTED_CURRENCY) {
            return;
        }

        throw new UnsupportedCurrencyException(
            'Razorpay only supports '.self::SUPPORTED_CURRENCY."; this payment is in {$currency}. "
            .'Configure a payment provider that settles in the marketplace currency.'
        );
    }

    private function client(): PendingRequest
    {
        return Http::withBasicAuth(
            (string) config('services.razorpay.key'),
            (string) config('services.razorpay.secret'),
        )
            ->timeout((int) config('services.razorpay.timeout'))
            ->acceptJson()
            ->asJson();
    }

    private function url(string $path): string
    {
        return rtrim((string) config('services.razorpay.base_url'), '/').$path;
    }
}
