<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One form for the two subject-request actions on /teacher/subjects:
 * `subject_ids[]` asks to teach catalog subjects, while `proposed_name` +
 * `education_level_id` propose a subject that is not in the catalog yet.
 */
class TeacherSubjectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->isTeacher() ?? false) && $this->user()->teacherProfile()->exists();
    }

    public function rules(): array
    {
        return [
            'subject_ids' => ['nullable', 'array', 'min:1', 'required_without:proposed_name'],
            'subject_ids.*' => ['integer', Rule::exists('subjects', 'id')->where('is_active', true)],
            'proposed_name' => ['nullable', 'string', 'max:100', 'required_without:subject_ids'],
            'education_level_id' => [
                'nullable',
                'integer',
                Rule::exists('education_levels', 'id'),
                'required_with:proposed_name',
            ],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
