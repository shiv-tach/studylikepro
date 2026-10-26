<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Wizard step 3 ("Teaching preferences"). Same rules as the workspace profile
 * form, but scoped to the fields this step actually renders.
 */
class TeacherOnboardingPreferencesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isTeacher() ?? false;
    }

    public function rules(): array
    {
        return [
            'languages' => ['required', 'array', 'min:1'],
            'languages.*' => ['string', Rule::in(config('studylikepro.languages'))],
            'hourly_rate' => ['required', 'numeric', 'min:100', 'max:100000'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=2048,max_height=2048'],
        ];
    }
}
