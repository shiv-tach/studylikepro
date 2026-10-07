<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class LessonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Slug is optional in the form; deriving it before validation keeps the
     * uniqueness check honest when the admin types only a name.
     */
    protected function prepareForValidation(): void
    {
        if (blank($this->input('slug')) && filled($this->input('name'))) {
            $slug = Str::slug((string) $this->input('name'));

            if ($slug !== '') {
                $this->merge(['slug' => $slug]);
            }
        }
    }

    public function rules(): array
    {
        $subject = $this->route('subject');
        $lesson = $this->route('lesson');

        return [
            'name' => ['required', 'string', 'max:100'],
            'grade_id' => [
                'required',
                'integer',
                Rule::exists('grades', 'id')
                    ->where('is_active', true)
                    ->where('education_level_id', $subject?->education_level_id),
            ],
            'slug' => [
                'nullable',
                'string',
                'max:100',
                'alpha_dash',
                Rule::unique('lessons', 'slug')
                    ->where('subject_id', $subject?->id)
                    ->where('grade_id', $this->input('grade_id'))
                    ->ignore($lesson?->id),
            ],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
