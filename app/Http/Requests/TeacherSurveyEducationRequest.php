<?php

namespace App\Http\Requests;

use App\Support\TeacherSurvey;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Survey question 6: the kind of institute the teacher studied at.
 */
class TeacherSurveyEducationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->routeIs('join.*');
    }

    public function rules(): array
    {
        return [
            'education' => ['required', 'string', Rule::in(TeacherSurvey::educationValues())],
        ];
    }

    public function messages(): array
    {
        return [
            'education.required' => __('Pick the institute you studied at.'),
        ];
    }
}
