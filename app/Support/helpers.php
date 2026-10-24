<?php

use App\Services\PlatformSettings;
use App\Support\PhoneNumber;
use App\Support\TimeFormat;
use Illuminate\Support\Carbon;

if (! function_exists('platform_settings')) {
    /**
     * Runtime-tunable marketplace settings (commission, booking fee, hold TTL,
     * refund policy).
     */
    function platform_settings(): PlatformSettings
    {
        return app(PlatformSettings::class);
    }
}

if (! function_exists('display_time')) {
    /**
     * A clock time as the user reads it, e.g. "6:00 PM".
     */
    function display_time(?DateTimeInterface $at): ?string
    {
        return $at?->format(TimeFormat::TIME);
    }
}

if (! function_exists('display_datetime')) {
    /**
     * A date and clock time as the user reads it, e.g. "12 Sep 2026, 6:00 PM".
     *
     * The date part and the separator are arguments because bookings are shown
     * in several shapes ("D d M, 6:00 PM", "D, d M Y · 6:00 PM").
     */
    function display_datetime(?DateTimeInterface $at, string $dateFormat = 'd M Y', string $separator = ', '): ?string
    {
        return $at?->format($dateFormat.$separator.TimeFormat::TIME);
    }
}

if (! function_exists('display_mobile')) {
    /**
     * A stored mobile number as people read it, e.g. "076 982 88 74".
     */
    function display_mobile(?string $mobile): ?string
    {
        return PhoneNumber::format($mobile);
    }
}

if (! function_exists('display_clock')) {
    /**
     * A stored 24-hour clock value ("18:30") as the user reads it ("6:30 PM").
     *
     * Availability ranges and slot previews are kept as plain "H:i" strings, so
     * they need parsing before they can be shown in the 12-hour format.
     */
    function display_clock(?string $value): ?string
    {
        return $value ? Carbon::createFromFormat('H:i', $value)->format(TimeFormat::TIME) : null;
    }
}
