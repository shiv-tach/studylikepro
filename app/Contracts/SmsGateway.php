<?php

namespace App\Contracts;

use App\Exceptions\SmsDeliveryException;

/**
 * Sends a plain text SMS to one Sri Lankan mobile number.
 *
 * Implementations receive the canonical local form ("0769828874") and are
 * responsible for converting it to whatever shape the provider expects.
 */
interface SmsGateway
{
    /**
     * Short identifier stored in logs and shown in the admin console.
     */
    public function name(): string;

    /**
     * Deliver one message, or throw when the provider rejects it.
     *
     * @throws SmsDeliveryException
     */
    public function send(string $mobile, string $message): void;
}
