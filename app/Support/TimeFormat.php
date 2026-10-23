<?php

namespace App\Support;

/**
 * The one place the app decides how a clock time is written for humans.
 *
 * Everything the user reads uses a 12-hour clock with a meridiem ("6:00 PM").
 * Machine-facing values deliberately keep the 24-hour form: `type="time"`
 * inputs, `datetime-local` values, CSV exports and the `date_format:H:i`
 * validation rules.
 */
final class TimeFormat
{
    /**
     * A clock time, e.g. "6:00 PM".
     */
    public const TIME = 'g:i A';

    /**
     * A date followed by a clock time, e.g. "12 Sep 2026, 6:00 PM".
     */
    public const DATE_TIME = 'd M Y, '.self::TIME;
}
