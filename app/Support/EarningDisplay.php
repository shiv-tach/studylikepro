<?php

namespace App\Support;

use App\Enums\EarningStatus;

/**
 * The presentation decisions an earning row shares across the teacher surfaces:
 * the semantic colour of its state and the glyph its chip carries.
 *
 * Kept next to {@see BookingDisplay} for the same reason: the tones are UI
 * choices that map onto Tailwind classes the Vue apps understand, and they must
 * not drift between the earnings ledger and the order page that shows one
 * earning.
 */
final class EarningDisplay
{
    /**
     * A semantic tone, independent of the shell's accent preset.
     *
     * Money that is merely waiting (amber) never looks like money that is ready
     * (emerald), which never looks like money already transferred (primary), and
     * a refunded row is unmistakable (rose).
     */
    public static function tone(EarningStatus $status): string
    {
        return match ($status) {
            EarningStatus::Pending => 'amber',
            EarningStatus::Eligible => 'emerald',
            EarningStatus::Paid => 'primary',
            EarningStatus::Reversed => 'rose',
        };
    }

    /**
     * The glyph a status chip carries, so the state is readable without colour.
     */
    public static function icon(EarningStatus $status): string
    {
        return match ($status) {
            EarningStatus::Pending => 'clock',
            EarningStatus::Eligible => 'wallet',
            EarningStatus::Paid => 'check-circle',
            EarningStatus::Reversed => 'alert',
        };
    }
}
