<?php

namespace App\Support;

/**
 * Suggested teaching weeks for the availability step. They are only editor
 * prefill: nothing is saved until the teacher submits the form, so loading a
 * preset never rewrites a week they already set up.
 *
 * Times are teacher-local clock times ("H:i"), matching
 * `teacher_availability_slots` minute columns.
 */
final class AvailabilityPresets
{
    /**
     * The week in the order every picker shows it. Short labels keep a preset's
     * times readable on one line ("Mon – Fri · 3:00 PM – 9:00 PM").
     */
    private const WEEK = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 0 => 'Sun'];

    /**
     * @return list<array{key: string, label: string, hint: string, recommended: bool, days: array<int, list<array{start: string, end: string}>>}>
     */
    public static function all(): array
    {
        $weekdayEvening = [['start' => '15:00', 'end' => '21:00']];
        $weekendDay = [['start' => '08:00', 'end' => '20:00']];

        return [
            [
                'key' => 'school_friendly',
                'label' => __('School-friendly'),
                'hint' => __('Weekdays after school, full days on weekends.'),
                'recommended' => true,
                'days' => [
                    1 => $weekdayEvening,
                    2 => $weekdayEvening,
                    3 => $weekdayEvening,
                    4 => $weekdayEvening,
                    5 => $weekdayEvening,
                    6 => $weekendDay,
                    0 => $weekendDay,
                ],
            ],
            [
                'key' => 'evenings',
                'label' => __('Evenings only'),
                'hint' => __('Short evening slots every day of the week.'),
                'recommended' => false,
                'days' => [
                    1 => [['start' => '17:00', 'end' => '21:00']],
                    2 => [['start' => '17:00', 'end' => '21:00']],
                    3 => [['start' => '17:00', 'end' => '21:00']],
                    4 => [['start' => '17:00', 'end' => '21:00']],
                    5 => [['start' => '17:00', 'end' => '21:00']],
                    6 => [['start' => '17:00', 'end' => '21:00']],
                    0 => [['start' => '17:00', 'end' => '21:00']],
                ],
            ],
            [
                'key' => 'weekends',
                'label' => __('Weekends only'),
                'hint' => __('Saturdays and Sundays, full days.'),
                'recommended' => false,
                'days' => [
                    6 => $weekendDay,
                    0 => $weekendDay,
                ],
            ],
        ];
    }

    /**
     * The presets as pickable options, each with the clock times it fills in, so
     * a screen that only offers a choice (the join survey) can show the same
     * times the availability editor pre-fills.
     *
     * @return list<array{key: string, label: string, hint: string, recommended: bool, days: array<int, list<array{start: string, end: string}>>, windows: list<array{days: string, time: string}>}>
     */
    public static function options(): array
    {
        return array_map(function (array $preset): array {
            $preset['windows'] = self::windows($preset['days']);

            return $preset;
        }, self::all());
    }

    /**
     * @return array{key: string, label: string, hint: string, recommended: bool, days: array<int, list<array{start: string, end: string}>>}|null
     */
    public static function find(?string $key): ?array
    {
        foreach (self::all() as $preset) {
            if ($preset['key'] === $key) {
                return $preset;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_map(fn (array $preset) => (string) $preset['key'], self::all());
    }

    /**
     * The preset the setup page pre-loads for a teacher with no ranges yet.
     *
     * @return array{key: string, label: string, hint: string, recommended: bool, days: array<int, list<array{start: string, end: string}>>}
     */
    public static function recommended(): array
    {
        return self::all()[0];
    }

    /**
     * A clock label for a list of ranges, e.g. "3:00 PM – 9:00 PM".
     *
     * @param  list<array{start: string, end: string}>  $ranges
     */
    public static function timeLabel(array $ranges): string
    {
        return implode(', ', array_map(
            fn (array $range) => display_clock($range['start']).' – '.display_clock($range['end']),
            $ranges,
        ));
    }

    /**
     * Every day of the week with the hours a day gets when the teacher picks
     * their own days: the recommended preset's times, and a weekday-evening
     * fallback for any day that preset does not cover.
     *
     * @return array<int, list<array{start: string, end: string}>>
     */
    public static function defaultWeek(): array
    {
        $recommended = self::recommended()['days'];
        $fallback = [['start' => '15:00', 'end' => '21:00']];
        $week = [];

        foreach (array_keys(self::WEEK) as $day) {
            $week[$day] = $recommended[$day] ?? $fallback;
        }

        return $week;
    }

    /**
     * One line per run of consecutive days that share the same times, e.g.
     * "Mon – Fri · 3:00 PM – 9:00 PM" and "Sat – Sun · 8:00 AM – 8:00 PM".
     *
     * @param  array<int, list<array{start: string, end: string}>>  $days
     * @return list<array{days: string, time: string}>
     */
    private static function windows(array $days): array
    {
        $groups = [];
        $previousDay = null;

        foreach (self::WEEK as $day => $short) {
            $ranges = $days[$day] ?? [];

            if ($ranges === []) {
                $previousDay = null;

                continue;
            }

            $signature = json_encode($ranges);
            $last = array_key_last($groups);

            if ($previousDay !== null && $last !== null && $groups[$last]['signature'] === $signature) {
                $groups[$last]['labels'][] = __($short);
            } else {
                $groups[] = ['signature' => $signature, 'labels' => [__($short)], 'ranges' => $ranges];
            }

            $previousDay = $day;
        }

        return array_map(fn (array $group) => [
            'days' => count($group['labels']) === 1
                ? $group['labels'][0]
                : $group['labels'][0].' – '.$group['labels'][count($group['labels']) - 1],
            'time' => self::timeLabel($group['ranges']),
        ], $groups);
    }
}
