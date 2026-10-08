<?php

namespace App\Http\Requests;

use App\Enums\ServiceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpsertServiceOfferingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->isTeacher() ?? false)
            && $this->user()->teacherProfile()->exists();
    }

    /**
     * Map the rupee price input to minor units and drop fields that do not
     * belong to the submitted service type before the rules run.
     */
    protected function prepareForValidation(): void
    {
        $price = $this->input('price');
        $type = (string) $this->input('service_type');

        $this->merge([
            'price_minor' => $price !== null && $price !== ''
                ? (int) round(((float) $price) * 100)
                : null,
            'subject_id' => $this->blankToNull('subject_id'),
            'lesson_id' => $this->blankToNull('lesson_id'),
            'allocation_minutes' => $this->blankToNull('allocation_minutes'),
            'default_session_minutes' => $this->blankToNull('default_session_minutes'),
            'max_sessions' => $this->blankToNull('max_sessions'),
            'max_pages' => $this->blankToNull('max_pages'),
            'turnaround_hours' => $this->blankToNull('turnaround_hours'),
        ]);

        $this->merge($this->scopedFieldsForType($type));

        // A Q&A scoped to a single lesson carries no subject scope.
        if ($type === ServiceType::Qna->value && $this->filled('lesson_id')) {
            $this->merge(['subject_id' => null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function scopedFieldsForType(string $type): array
    {
        return [
            // Only lesson plans carry a total time allocation.
            'allocation_minutes' => $type === ServiceType::Lesson->value
                ? $this->input('allocation_minutes')
                : null,
            // Paper services are subject-wide, never scoped to one lesson.
            'lesson_id' => in_array($type, [
                ServiceType::PaperMarking->value,
                ServiceType::PaperDiscussion->value,
            ], true) ? null : $this->input('lesson_id'),
            // Paper discussions and Q&A are always a single session.
            'max_sessions' => match ($type) {
                ServiceType::Lesson->value => $this->input('max_sessions'),
                ServiceType::Qna->value, ServiceType::PaperDiscussion->value => 1,
                default => null,
            },
            // Page limits and the turnaround SLA only apply to paper marking.
            'max_pages' => $type === ServiceType::PaperMarking->value ? $this->input('max_pages') : null,
            'turnaround_hours' => $type === ServiceType::PaperMarking->value ? $this->input('turnaround_hours') : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'service_type' => ['required', Rule::enum(ServiceType::class)],
            'title' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:1', 'max:1000000'],
            'price_minor' => ['nullable', 'integer', 'min:1'],
            'subject_id' => [
                'nullable',
                'required_unless:service_type,qna',
                'integer',
                Rule::exists('subjects', 'id')->where('is_active', true),
            ],
            'lesson_id' => ['nullable', 'integer', 'exists:lessons,id'],
            'allocation_minutes' => ['nullable', 'integer', 'min:30', 'max:6000'],
            'default_session_minutes' => ['nullable', 'integer', Rule::in(config('studylikepro.lesson_durations'))],
            'max_sessions' => ['nullable', 'integer', 'min:1', 'max:20'],
            'max_pages' => ['nullable', 'integer', 'min:1', 'max:500'],
            'turnaround_hours' => ['nullable', 'integer', 'min:1', 'max:720'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $type = ServiceType::tryFrom((string) $this->input('service_type'));

            if ($type === null) {
                return; // the enum rule reports the unknown type
            }

            $hasSubject = $this->filled('subject_id');
            $hasLesson = $this->filled('lesson_id');

            if ($type === ServiceType::Lesson) {
                if (! $hasLesson && ! $validator->errors()->has('lesson_id')) {
                    $validator->errors()->add('lesson_id', __('Lesson plans need a lesson.'));
                }

                if (! $hasSubject && ! $validator->errors()->has('subject_id')) {
                    $validator->errors()->add('subject_id', __('Lesson plans need a subject.'));
                }

                if (! $this->filled('allocation_minutes') && ! $validator->errors()->has('allocation_minutes')) {
                    $validator->errors()->add('allocation_minutes', __('Set the total time for the lesson plan.'));
                }
            }

            if ($type === ServiceType::Summary && ! $hasLesson && ! $validator->errors()->has('lesson_id')) {
                $validator->errors()->add('lesson_id', __('Lesson summaries need a lesson.'));
            }

            if ($type === ServiceType::PaperMarking && ! $hasSubject && ! $validator->errors()->has('subject_id')) {
                $validator->errors()->add('subject_id', __('Paper marking needs a subject.'));
            }

            if ($type === ServiceType::PaperDiscussion && ! $hasSubject && ! $validator->errors()->has('subject_id')) {
                $validator->errors()->add('subject_id', __('Paper discussion needs a subject.'));
            }

            if ($type === ServiceType::Qna && ! $hasSubject && ! $hasLesson) {
                $validator->errors()->add('subject_id', __('Q&A needs a lesson or a subject.'));
            }
        });
    }

    private function blankToNull(string $key): mixed
    {
        $value = $this->input($key);

        return ($value === null || $value === '') ? null : $value;
    }
}
