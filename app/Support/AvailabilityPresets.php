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
     * The preset the setup page pre-loads for a teacher with no ranges yet.
     *
     * @return array{key: string, label: string, hint: string, recommended: bool, days: array<int, list<array{start: string, end: string}>>}
     */
    public static function recommended(): array
    {
        return self::all()[0];
    }
}
