<?php

namespace App\Support\Payments;

final readonly class GatewayRefund
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $id,
        public string $status,
        public int $amountMinor,
        public array $payload = [],
    ) {}

    public function isProcessed(): bool
    {
        return in_array($this->status, ['processed', 'refunded'], true);
    }
}
