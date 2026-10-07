<?php

namespace App\Enums;

enum BookingFeeDiscountType: string
{
    case Waive = 'waive';
    case Percent = 'percent';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Waive => 'Waived',
            self::Percent => 'Percentage off',
            self::Fixed => 'Fixed amount off',
        };
    }

    /**
     * How much of a fee this discount type takes off, in minor units.
     */
    public function discountFor(int $feeMinor, int $value): int
    {
        return match ($this) {
            self::Waive => $feeMinor,
            self::Percent => (int) round($feeMinor * min(100, max(0, $value)) / 100),
            self::Fixed => min($feeMinor, max(0, $value)),
        };
    }
}
