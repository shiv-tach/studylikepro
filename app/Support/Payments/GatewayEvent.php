<?php

namespace App\Support\Payments;

use Illuminate\Support\Arr;

/**
 * A normalised provider webhook: the event identity used for idempotency plus
 * whichever payment identifiers the event carries.
 */
final readonly class GatewayEvent
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $id,
        public string $type,
        public ?string $paymentId = null,
        public ?string $orderId = null,
        public ?int $amountMinor = null,
        public ?string $status = null,
        public ?string $method = null,
        public ?string $failureReason = null,
        public array $payload = [],
    ) {}

    /**
     * Build from a Razorpay-shaped body: { event, payload: { payment: { entity } } }.
     *
     * @param  array<string, mixed>  $body
     */
    public static function fromRazorpayBody(array $body, ?string $eventIdHeader = null): self
    {
        $entity = Arr::get($body, 'payload.payment.entity', []);
        $type = (string) ($body['event'] ?? 'unknown');

        return new self(
            id: $eventIdHeader ?: hash('sha256', (string) json_encode($body)),
            type: $type,
            paymentId: $entity['id'] ?? null,
            orderId: $entity['order_id'] ?? null,
            amountMinor: isset($entity['amount']) ? (int) $entity['amount'] : null,
            status: $entity['status'] ?? null,
            method: $entity['method'] ?? null,
            failureReason: $entity['error_description'] ?? null,
            payload: $body,
        );
    }

    public function isCapture(): bool
    {
        return in_array($this->type, ['payment.captured', 'payment.authorized'], true)
            && $this->status === 'captured';
    }

    public function isFailure(): bool
    {
        return $this->type === 'payment.failed';
    }

    public function isRefund(): bool
    {
        return str_starts_with($this->type, 'refund.');
    }
}
