<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The "copy lessons to another grade" bulk tool.
 */
class CopyLessonsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $subject = $this->route('subject');
        $levelId = $subject?->education_level_id;

        return [
            'from_grade_id' => [
                'required',
                'integer',
                Rule::exists('grades', 'id')->where('education_level_id', $levelId),
            ],
            'to_grade_id' => [
                'required',
                'integer',
                Rule::exists('grades', 'id')->where('education_level_id', $levelId),
                'different:from_grade_id',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to_grade_id.different' => 'Pick a different grade to copy into.',
        ];
    }
}
