<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * Teacher search as the logged-in student sees it: the public filters minus
 * everything derived from the profile (level, grade, lesson) plus a concrete
 * date and the earliest-availability sort. The grade is never read from the
 * query string — it always comes from the student's profile.
 */
class StudentTeacherSearchRequest extends TeacherSearchRequest
{
    public function rules(): array
    {
        $rules = parent::rules();

        unset($rules['level'], $rules['lesson'], $rules['grade'], $rules['weekday'], $rules['min_rate'], $rules['max_rate']);

        $rules['date'] = array_values(array_filter([
            'nullable', 'date_format:Y-m-d', 'after_or_equal:today',
            $this->filled('time_from') || $this->filled('time_to') ? 'required' : null,
        ]));
        $rules['time_from'] = ['nullable', 'date_format:H:i', 'required_with:time_to'];
        $rules['time_to'] = array_values(array_filter([
            'nullable', 'date_format:H:i',
            $this->filled('time_from') ? 'after:time_from' : null,
        ]));
        $rules['sort'] = ['nullable', Rule::in(['available_soon', 'rating', 'price_low', 'price_high', 'newest'])];

        return $rules;
    }

    public function sort(): string
    {
        return $this->validated('sort') ?? 'available_soon';
    }
}
