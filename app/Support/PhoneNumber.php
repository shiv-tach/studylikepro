<?php

namespace App\Support;

/**
 * Sri Lankan mobile numbers, in the one shape the app stores and compares.
 *
 * People type the same number in many ways — "076 982 88 74", "+94 76 982
 * 8874", "076-9828874" — so everything is normalised to the ten local digits
 * ("0769828874") before it is validated, stored, searched or used to sign in.
 * The display helper turns those digits back into "076 982 88 74".
 */
class PhoneNumber
{
    /** A Sri Lankan mobile: 07X XXX XXXX (07[0-8] plus seven digits). */
    public const PATTERN = '/^07[0-8]\d{7}$/';

    /** The format shown to people, e.g. "076 982 88 74". */
    public const DISPLAY_EXAMPLE = '076 982 88 74';

    /**
     * Convert any accepted spelling to the canonical ten local digits, or null
     * when it is not a valid Sri Lankan mobile number.
     */
    public static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        // Keep digits only; a leading "+" is handled below via its 94 prefix.
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if ($digits === '') {
            return null;
        }

        // +94 76…, 0094 76… and 94 76… all become 076…
        if (str_starts_with($digits, '0094')) {
            $digits = '0'.substr($digits, 4);
        } elseif (str_starts_with($digits, '94') && strlen($digits) === 11) {
            $digits = '0'.substr($digits, 2);
        } elseif (str_starts_with($digits, '7') && strlen($digits) === 9) {
            // 76 982 8874 — the international body without its trunk/prefix 0.
            $digits = '0'.$digits;
        }

        return preg_match(self::PATTERN, $digits) === 1 ? $digits : null;
    }

    /**
     * Whether the value is a usable Sri Lankan mobile number.
     */
    public static function isValid(?string $value): bool
    {
        return self::normalize($value) !== null;
    }

    /**
     * A stored number as people read it: "0769828874" → "076 982 88 74".
     *
     * Values that are not valid numbers are returned unchanged so nothing is
     * silently hidden from an admin screen.
     */
    public static function format(?string $value): ?string
    {
        $digits = self::normalize($value);

        if ($digits === null) {
            return $value === null || trim($value) === '' ? null : trim($value);
        }

        return substr($digits, 0, 3).' '.substr($digits, 3, 3).' '.substr($digits, 6, 2).' '.substr($digits, 8, 2);
    }

    /**
     * The number in international form: "0769828874" → "+94769828874".
     *
     * SMSLenz expects its `contact` parameter in exactly this shape.
     */
    public static function international(?string $value): ?string
    {
        $digits = self::normalize($value);

        return $digits === null ? null : '+94'.substr($digits, 1);
    }

    /**
     * A number safe to show on screen before it is verified:
     * "0769828874" → "076 ••• 88 74".
     */
    public static function mask(?string $value): ?string
    {
        $digits = self::normalize($value);

        if ($digits === null) {
            return null;
        }

        return substr($digits, 0, 3).' ••• '.substr($digits, 6, 2).' '.substr($digits, 8, 2);
    }
}
