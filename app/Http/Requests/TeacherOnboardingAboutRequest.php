<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Wizard step 2 ("About you"). Only the fields of this step are validated, so
 * the step can be saved before the preferences step has ever been opened.
 */
class TeacherOnboardingAboutRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The invite wizard (join.*) collects this step before the account
        // exists, so a guest is allowed here as well.
        return ($this->user()?->isTeacher() ?? false) || $this->routeIs('join.*');
    }

    public function rules(): array
    {
        return [
            'headline' => ['required', 'string', 'max:120'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'experience_years' => ['required', 'integer', 'min:0', 'max:60'],
            'education' => ['required', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'experience_years' => __('years of experience'),
        ];
    }
}
