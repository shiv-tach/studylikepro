<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StudentProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStudent() ?? false;
    }

    public function rules(): array
    {
        return [
            'grade_id' => ['required', 'integer', Rule::exists('grades', 'id')->where('is_active', true)],
            'learning_goals' => ['nullable', 'string', 'max:1000'],
            'guardian_name' => ['nullable', 'string', 'max:120'],
            'guardian_phone' => ['nullable', 'string', 'max:32'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:max_width=2048,max_height=2048'],
        ];
    }
}
