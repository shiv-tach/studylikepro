<?php

namespace App\Contracts;

/**
 * Anything a student can pay for at checkout: a booking hold or a service
 * purchase. The payment gateway only needs these accessors, so checkout
 * plumbing stays identical for both.
 */
interface Payable
{
    public function getId(): int;

    /**
     * What the student is charged at checkout, in minor units.
     */
    public function totalMinor(): int;

    public function getCurrency(): string;

    public function studentId(): ?int;

    public function teacherProfileId(): ?int;

    /**
     * Discriminator stored in provider payloads ('booking' | 'purchase').
     */
    public function payableType(): string;

    /**
     * Human label used in provider notes and receipts.
     */
    public function payableLabel(): string;
}
