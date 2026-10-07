<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequestLessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tutoringRequest = $this->route('tutoringRequest');

        $lessonRule = Rule::exists('lessons', 'id')
            ->where('subject_id', $this->input('subject_id'))
            ->where('is_active', true);

        // A request with a grade only accepts lessons of that grade.
        if ($tutoringRequest?->grade_id !== null) {
            $lessonRule->where('grade_id', $tutoringRequest->grade_id);
        }

        return [
            'subject_id' => ['required', 'integer', Rule::exists('subjects', 'id')->where('is_active', true)],
            'lesson_id' => ['required', 'integer', $lessonRule],
        ];
    }
}
