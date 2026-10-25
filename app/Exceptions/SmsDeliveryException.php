<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * An SMS could not be handed to the provider (bad credentials, network error,
 * rejected sender ID, …). Registration keeps the pending form and offers a
 * resend instead of losing the visitor's details.
 */
class SmsDeliveryException extends RuntimeException {}
