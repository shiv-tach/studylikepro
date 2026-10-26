<?php

namespace App\Http\Requests;

use App\Support\TeacherOnboarding;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The autosave endpoint receives half-typed answers, so it only checks that a
 * value is of the right type and cannot exceed what the real save accepts —
 * nothing here is `required`.
 */
class TeacherOnboardingDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isTeacher() ?? false;
    }

    public function rules(): array
    {
        $base = [
            'step' => ['required', 'string', Rule::in(TeacherOnboarding::DATA_STEPS)],
            'data' => ['present', 'array'],
        ];

        return array_merge($base, match ($this->input('step')) {
            TeacherOnboarding::STEP_ABOUT => [
                'data.headline' => ['nullable', 'string', 'max:120'],
                'data.bio' => ['nullable', 'string', 'max:2000'],
                'data.experience_years' => ['nullable', 'numeric', 'min:0', 'max:60'],
                'data.education' => ['nullable', 'string', 'max:255'],
            ],
            TeacherOnboarding::STEP_PREFERENCES => [
                'data.languages' => ['nullable', 'array'],
                'data.languages.*' => ['string', Rule::in(config('studylikepro.languages'))],
                'data.hourly_rate' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            ],
            TeacherOnboarding::STEP_AVAILABILITY => [
                'data.days' => ['nullable', 'array', 'max:7'],
                'data.days.*.value' => ['nullable', 'integer', 'min:0', 'max:6'],
                'data.days.*.enabled' => ['nullable', 'boolean'],
                'data.days.*.ranges' => ['nullable', 'array', 'max:4'],
                'data.days.*.ranges.*.start' => ['nullable', 'string', 'max:5'],
                'data.days.*.ranges.*.end' => ['nullable', 'string', 'max:5'],
            ],
            TeacherOnboarding::STEP_VERIFICATION => [
                'data.agree' => ['nullable', 'boolean'],
            ],
            default => [],
        });
    }
}
