<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A registration could not be completed because the details changed while the
 * passcode was in flight (the email or mobile was taken, or a teacher's invite
 * was consumed or expired). The controller sends the visitor back to the form
 * with their input and the conflict explained.
 */
class RegistrationConflictException extends RuntimeException
{
    /**
     * @param  array<string, string>  $errors
     * @param  array<string, mixed>  $input
     */
    public function __construct(
        public readonly array $errors,
        public readonly array $input = [],
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : (string) reset($errors));
    }
}
