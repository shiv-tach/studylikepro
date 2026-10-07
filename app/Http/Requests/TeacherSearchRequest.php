<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeacherSearchRequest extends FormRequest
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
        return [
            'level' => ['nullable', 'string', Rule::exists('education_levels', 'key')->where('is_active', true)],
            'subject' => ['nullable', 'string', 'exists:subjects,slug,is_active,1'],
            'lesson' => ['nullable', 'integer', Rule::exists('lessons', 'id')->where('is_active', true)],
            'grade' => ['nullable', 'integer', Rule::exists('grades', 'id')->where('is_active', true)],
            'language' => ['nullable', 'string', Rule::in(config('studylikepro.languages'))],
            'min_rate' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'max_rate' => array_values(array_filter([
                'nullable', 'numeric', 'min:0', 'max:100000',
                $this->filled('min_rate') ? 'gte:min_rate' : null,
            ])),
            'weekday' => ['nullable', 'integer', 'between:0,6', 'required_with:time_from,time_to'],
            'time_from' => ['nullable', 'date_format:H:i', 'required_with:weekday'],
            'time_to' => array_values(array_filter([
                'nullable', 'date_format:H:i',
                $this->filled('time_from') ? 'after:time_from' : null,
            ])),
            'sort' => ['nullable', Rule::in(['rating', 'price_low', 'price_high', 'newest'])],
        ];
    }

    public function sort(): string
    {
        return $this->validated('sort') ?? 'rating';
    }

    public function hasFilters(): bool
    {
        return collect(['level', 'subject', 'lesson', 'grade', 'language', 'min_rate', 'max_rate', 'weekday', 'time_from', 'time_to'])
            ->contains(fn (string $key) => filled($this->input($key)));
    }
}
