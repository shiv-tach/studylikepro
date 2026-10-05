<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * The video provider could not open a classroom. The booking records the
 * message so admins can see what went wrong and regenerate the link.
 */
class MeetingProvisioningException extends RuntimeException {}
