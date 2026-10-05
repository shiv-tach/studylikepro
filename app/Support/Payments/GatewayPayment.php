<?php

namespace App\Support\Payments;

final readonly class GatewayPayment
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $id,
        public string $status,
        public int $amountMinor,
        public string $currency,
        public ?string $method = null,
        public ?string $failureReason = null,
        public array $payload = [],
    ) {}

    public function isCaptured(): bool
    {
        return in_array($this->status, ['captured', 'paid'], true);
    }

    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }
}
