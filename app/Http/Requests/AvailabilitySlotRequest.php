<?php

namespace App\Http\Requests;

use App\Models\TeacherAvailabilitySlot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class AvailabilitySlotRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'day_of_week' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i'],
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

            $start = TeacherAvailabilitySlot::toMinute($this->input('start_time'));
            $end = TeacherAvailabilitySlot::toMinute($this->input('end_time'));

            if ($end <= $start) {
                $validator->errors()->add('end_time', __('The end time must be after the start time.'));

                return;
            }

            if ($end - $start < $duration) {
                $validator->errors()->add('end_time', __('A range must last at least your lesson length (:minutes minutes).', [
                    'minutes' => $duration,
                ]));

                return;
            }

            $overlapping = $profile?->availabilitySlots()
                ->where('day_of_week', (int) $this->input('day_of_week'))
                ->get()
                ->first(fn (TeacherAvailabilitySlot $slot) => $slot->overlaps($start, $end));

            if ($overlapping) {
                $validator->errors()->add('start_time', __('This range overlaps your existing :start–:end slot.', [
                    'start' => $overlapping->startLabel(),
                    'end' => $overlapping->endLabel(),
                ]));
            }
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function slotAttributes(int $teacherProfileId): array
    {
        return [
            'teacher_profile_id' => $teacherProfileId,
            'day_of_week' => (int) $this->validated('day_of_week'),
            'start_minute' => TeacherAvailabilitySlot::toMinute($this->validated('start_time')),
            'end_minute' => TeacherAvailabilitySlot::toMinute($this->validated('end_time')),
        ];
    }
}
