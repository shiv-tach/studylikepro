<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'topic_id' => ['nullable', 'integer', 'exists:topics,id'],
            'duration' => ['required', 'integer', 'in:'.implode(',', config('studylikepro.lesson_durations'))],
            'starts_at' => ['required', 'date'],
            'learner_name' => ['nullable', 'string', 'max:120'],
            'learner_grade' => ['nullable', 'string', 'in:'.implode(',', array_keys(config('studylikepro.grade_levels')))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'subject_id.required' => 'Choose the subject for this lesson.',
            'duration.in' => 'Pick one of the lesson lengths this teacher offers.',
            'starts_at.required' => 'Choose a time slot for the lesson.',
        ];
    }
}
