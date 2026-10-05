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
        return [
            'subjects' => ['nullable', 'array'],
            'subjects.*' => ['integer', Rule::exists('subjects', 'id')->where('is_active', true)],
            'topics' => ['nullable', 'array'],
            'topics.*' => ['integer', Rule::exists('topics', 'id')->where('is_active', true)],
        ];
    }
}
