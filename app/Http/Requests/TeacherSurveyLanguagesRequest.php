<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Survey question 3: the languages the teacher teaches in. More than one is
 * allowed and common (Sinhala + English).
 */
class TeacherSurveyLanguagesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->routeIs('join.*');
    }

    public function rules(): array
    {
        return [
            'languages' => ['required', 'array', 'min:1'],
            'languages.*' => ['string', 'distinct', Rule::in(config('studylikepro.languages'))],
        ];
    }

    public function messages(): array
    {
        return [
            'languages.required' => __('Pick at least one language you teach in.'),
            'languages.min' => __('Pick at least one language you teach in.'),
        ];
    }
}
