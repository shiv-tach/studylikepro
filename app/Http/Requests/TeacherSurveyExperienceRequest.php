<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Survey question 5: how many years the teacher has been teaching. The same
 * 0-60 range the "About you" onboarding step accepts, so an answer given here
 * can never be rejected there.
 */
class TeacherSurveyExperienceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->routeIs('join.*');
    }

    public function rules(): array
    {
        return [
            'experience_years' => ['required', 'integer', 'min:0', 'max:60'],
        ];
    }

    public function messages(): array
    {
        return [
            'experience_years.required' => __('Tell us how many years you have been teaching — enter 0 if you are just starting.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'experience_years' => __('years of experience'),
        ];
    }
}
