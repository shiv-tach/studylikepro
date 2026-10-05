<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TeachingSetupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ($this->user()?->isTeacher() ?? false) && $this->user()->teacherProfile()->exists();
    }

    public function rules(): array
    {
        return [
            'subjects' => ['nullable', 'array'],
            'subjects.*' => ['integer', Rule::exists('subjects', 'id')->where('is_active', true)],
        ];
    }
}
