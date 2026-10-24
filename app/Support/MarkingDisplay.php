<?php

namespace App\Support;

use App\Enums\MarkingOrderStatus;

/**
 * The presentation decisions the paper-marking surfaces share: the semantic
 * colour of a status, the glyph its chip carries, the one action a card offers
 * and the flash a delivered marking leaves behind.
 *
 * Kept next to {@see BookingDisplay} and {@see EarningDisplay} for the same
 * reason: the tones are UI choices that map onto Tailwind classes the Vue apps
 * understand, and they must not drift between the marking inbox, the marking
 * order console and the order book that links to both.
 */
final class MarkingDisplay
{
    /**
     * A semantic tone, independent of the shell's accent preset.
     *
     * Work still to do (amber) never looks like work in hand (primary), which
     * never looks like a delivery already with the student (sky/emerald), and a
     * dispute is unmistakable (rose).
     */
    public static function tone(MarkingOrderStatus $status): string
    {
        return match ($status) {
            MarkingOrderStatus::Submitted => 'amber',
            MarkingOrderStatus::InProgress => 'primary',
            MarkingOrderStatus::Returned => 'sky',
            MarkingOrderStatus::Accepted => 'emerald',
            MarkingOrderStatus::Disputed => 'rose',
            MarkingOrderStatus::Resolved, MarkingOrderStatus::Cancelled => 'slate',
        };
    }

    /**
     * The glyph a status chip carries, so the state is readable without colour.
     */
    public static function icon(MarkingOrderStatus $status): string
    {
        return match ($status) {
            MarkingOrderStatus::Submitted => 'clipboard',
            MarkingOrderStatus::InProgress => 'pencil',
            MarkingOrderStatus::Returned => 'check-circle',
            MarkingOrderStatus::Accepted => 'check',
            MarkingOrderStatus::Disputed => 'alert',
            MarkingOrderStatus::Resolved => 'shield-check',
            MarkingOrderStatus::Cancelled => 'close',
        };
    }

    /**
     * The one thing the teacher can do with this order, phrased as the work it
     * opens — never as "view details".
     */
    public static function actionLabel(MarkingOrderStatus $status): string
    {
        return match ($status) {
            MarkingOrderStatus::Submitted => __('Mark paper'),
            MarkingOrderStatus::InProgress => __('Continue marking'),
            MarkingOrderStatus::Returned => __('View delivery'),
            default => __('View order'),
        };
    }

    /**
     * The emerald banner a delivered marking leaves behind.
     *
     * @return array{title: string, message: string, tone: string, icon: string}|null
     */
    public static function flash(?string $status = null): ?array
    {
        $status ??= session('status');

        if ($status !== 'marking-returned') {
            return null;
        }

        return [
            'title' => __('Marked copy delivered'),
            'message' => __('Marked copy delivered — the student has been notified.'),
            'tone' => 'emerald',
            'icon' => 'check-circle',
        ];
    }

    /**
     * "Aarav Mehta" → "AM"; a single word falls back to its first two letters.
     */
    public static function initials(string $name): string
    {
        $initials = collect(preg_split('/\s+/', trim($name)) ?: [])
            ->filter()
            ->take(2)
            ->map(fn (string $part) => mb_substr($part, 0, 1))
            ->implode('');

        return mb_strtoupper($initials !== '' ? $initials : mb_substr($name, 0, 2));
    }
}
