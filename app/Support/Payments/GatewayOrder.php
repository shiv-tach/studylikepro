<?php

namespace App\Support\Payments;

final readonly class GatewayOrder
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public string $id,
        public int $amountMinor,
        public string $currency,
        public array $payload = [],
    ) {}
}
