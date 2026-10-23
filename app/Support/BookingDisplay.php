<?php

namespace App\Support;

use App\Enums\BookingStatus;

/**
 * The small, repeated presentation decisions a booking surface shares: the
 * semantic colour of a status, the flash a transition leaves behind, and the
 * initials an avatar shows.
 *
 * Kept out of the enums because the tones are UI choices (they map onto
 * Tailwind classes the Vue apps understand), and out of each support class so
 * the schedule and one lesson never drift apart.
 */
final class BookingDisplay
{
    /**
     * A semantic tone that is independent of the shell's accent preset.
     */
    public static function tone(BookingStatus $status): string
    {
        return match ($status) {
            BookingStatus::PendingPayment => 'amber',
            BookingStatus::Confirmed, BookingStatus::Completed => 'emerald',
            BookingStatus::InProgress => 'primary',
            BookingStatus::NoShow, BookingStatus::Disputed => 'rose',
            BookingStatus::Cancelled, BookingStatus::Expired, BookingStatus::Resolved => 'slate',
        };
    }

    /**
     * The banner a transition redirect left behind, if any.
     *
     * @return array{message: string, tone: string, icon: string|null}|null
     */
    public static function flash(?string $status = null): ?array
    {
        $status ??= session('status');

        $flash = match ($status) {
            'booking-start' => [
                'message' => __('Lesson started — the classroom opens for the student now.'),
                'tone' => 'primary',
                'icon' => 'play',
            ],
            'booking-complete' => [
                'message' => __('Lesson marked as delivered.'),
                'tone' => 'emerald',
                'icon' => 'check-circle',
            ],
            'booking-markNoShow' => [
                'message' => __('Marked as a no-show.'),
                'tone' => 'rose',
                'icon' => 'alert',
            ],
            'booking-cancelled' => [
                'message' => __('Lesson cancelled. The student has been notified.'),
                'tone' => 'slate',
                'icon' => 'close',
            ],
            default => null,
        };

        return $flash;
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
