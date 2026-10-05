<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubjectSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->isTeacher() ?? false) && $this->user()->teacherProfile()->exists();
    }

    public function rules(): array
    {
        $subject = $this->route('subject');

        return [
            'topics' => ['nullable', 'array'],
            'topics.*' => ['integer', Rule::exists('topics', 'id')->where('subject_id', $subject?->id)],
            'grade_levels' => ['nullable', 'array'],
            'grade_levels.*' => ['string', Rule::in(array_keys(config('studylikepro.grade_levels')))],
            'rate_per_hour' => ['nullable', 'numeric', 'min:100', 'max:100000'],
        ];
    }
}
