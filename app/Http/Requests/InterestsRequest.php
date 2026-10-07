<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InterestsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStudent() ?? false;
    }

    public function rules(): array
    {
        $user = $this->user();
        $grade = $user?->studentProfile?->grade;

        $subjectRule = Rule::exists('subjects', 'id')->where('is_active', true);

        if ($grade !== null) {
            $subjectRule->where('education_level_id', $grade->education_level_id);
        }

        return [
            'subjects' => ['nullable', 'array'],
            'subjects.*' => ['integer', $subjectRule],
            'lessons' => ['nullable', 'array'],
            'lessons.*' => ['integer', Rule::exists('lessons', 'id')->where('is_active', true)],
        ];
    }
}
