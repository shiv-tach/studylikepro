<?php

namespace App\Enums;

/**
 * How the marketplace prices the student booking fee: a flat amount per
 * booking, or a percentage of the lesson/service price being sold.
 */
enum BookingFeeType: string
{
    case Fixed = 'fixed';
    case Percent = 'percent';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed amount',
            self::Percent => 'Percentage of the price',
        };
    }

    /**
     * The fee this type produces for a given price, in minor units.
     *
     * A percentage fee needs the price being sold; without one there is
     * nothing to charge, so it falls back to zero.
     */
    public function feeFor(?int $priceMinor, int $fixedMinor, float $percent): int
    {
        return match ($this) {
            self::Fixed => max(0, $fixedMinor),
            self::Percent => $priceMinor === null
                ? 0
                : (int) round(max(0, $priceMinor) * min(100, max(0, $percent)) / 100),
        };
    }

    /**
     * Render a percentage for display, e.g. "10%" or "12.5%".
     */
    public static function percentLabel(float $percent): string
    {
        return rtrim(rtrim(number_format($percent, 2, '.', ''), '0'), '.').'%';
    }
}
