<?php

namespace App\Http\Requests;

use App\Models\TeacherAvailabilitySlot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * The whole week in one request: the availability step submits every day at
 * once (a day is "on" when it carries an enabled flag), so a rejected save can
 * hand the exact rows back and the ranges are replaced atomically.
 *
 * Field names are positional (`days[0][ranges][1][start_time]`), which keeps
 * the error keys aligned with the rows the Vue editor renders.
 */
class WeeklyAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isTeacher() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'days' => ['required', 'array', 'min:1'],
            'days.*.value' => ['required', 'integer', 'between:0,6', 'distinct'],
            'days.*.enabled' => ['nullable', 'boolean'],
            'days.*.ranges' => ['nullable', 'array', 'max:4'],
            'days.*.ranges.*.start_time' => ['nullable', 'date_format:H:i'],
            'days.*.ranges.*.end_time' => ['nullable', 'date_format:H:i'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $profile = $this->user()->teacherProfile;
            $duration = $profile?->lessonDuration() ?? 60;
            $weekdays = config('studylikepro.weekdays');
            $saved = 0;

            foreach ($this->input('days', []) as $index => $day) {
                if (! $this->enabled($day['enabled'] ?? false)) {
                    continue;
                }

                $ranges = array_values($day['ranges'] ?? []);
                $dayLabel = $weekdays[(int) $day['value']] ?? __('this day');

                if ($ranges === []) {
                    $validator->errors()->add(
                        "days.$index.ranges",
                        __('Add at least one time range for :day.', ['day' => $dayLabel]),
                    );

                    continue;
                }

                $intervals = [];

                foreach ($ranges as $row => $range) {
                    $startTime = $range['start_time'] ?? null;
                    $endTime = $range['end_time'] ?? null;

                    if (blank($startTime) || blank($endTime)) {
                        $validator->errors()->add(
                            "days.$index.ranges.$row.start_time",
                            __('Both a start and an end time are required.'),
                        );

                        continue;
                    }

                    $start = TeacherAvailabilitySlot::toMinute($startTime);
                    $end = TeacherAvailabilitySlot::toMinute($endTime);

                    if ($end <= $start) {
                        $validator->errors()->add(
                            "days.$index.ranges.$row.end_time",
                            __('The end time must be after the start time.'),
                        );

                        continue;
                    }

                    if ($end - $start < $duration) {
                        $validator->errors()->add(
                            "days.$index.ranges.$row.end_time",
                            __('A range must last at least your lesson length (:minutes minutes).', [
                                'minutes' => $duration,
                            ]),
                        );

                        continue;
                    }

                    foreach ($intervals as [$otherStart, $otherEnd]) {
                        if ($start < $otherEnd && $end > $otherStart) {
                            $validator->errors()->add(
                                "days.$index.ranges.$row.start_time",
                                __('This range overlaps another range on the same day.'),
                            );

                            break;
                        }
                    }

                    $intervals[] = [$start, $end];
                    $saved++;
                }
            }

            if ($saved === 0 && $validator->errors()->isEmpty()) {
                $validator->errors()->add(
                    'days',
                    __('Add at least one weekly range so students can book you.'),
                );
            }
        });
    }

    /**
     * Days the teacher switched off are ignored, even when their old times are
     * still in the payload (the no-JS form always submits every day).
     */
    private function enabled(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
