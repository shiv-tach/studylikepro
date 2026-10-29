<?php

namespace App\Http\Requests;

use App\Support\AvailabilityPresets;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Survey question 8: the days of the week the teacher can teach, plus the
 * suggested week (with its clock times) they started from. The wizard's
 * availability step opens on those times, so at least one day is required.
 */
class TeacherSurveyAvailabilityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->routeIs('join.*');
    }

    public function rules(): array
    {
        return [
            'days' => ['required', 'array', 'min:1'],
            'days.*' => ['integer', 'between:0,6', 'distinct'],
            'preset' => ['nullable', 'string', Rule::in(AvailabilityPresets::keys())],
        ];
    }

    public function messages(): array
    {
        return [
            'days.required' => __('Pick at least one day you can teach.'),
            'days.min' => __('Pick at least one day you can teach.'),
            'preset.in' => __('Pick one of the suggested weeks.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'days' => __('teaching days'),
            'preset' => __('suggested week'),
        ];
    }
}
