<?php

namespace App\Support\Payments;

/**
 * Razorpay signs webhooks with HMAC-SHA256 over the raw request body.
 */
class WebhookSigner
{
    public static function sign(string $payload, string $secret): string
    {
        return hash_hmac('sha256', $payload, $secret);
    }

    public static function verify(string $payload, ?string $signature, string $secret): bool
    {
        if ($signature === null || $signature === '') {
            return false;
        }

        return hash_equals(self::sign($payload, $secret), $signature);
    }
}
