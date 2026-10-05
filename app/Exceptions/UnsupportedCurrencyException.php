<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The payment provider cannot settle in the booking's currency. Razorpay, for
 * instance, only supports INR — an LKR booking has to go to a provider that
 * handles Sri Lankan rupees, so this fails loudly instead of sending an order
 * the provider will reject.
 */
class UnsupportedCurrencyException extends RuntimeException {}
