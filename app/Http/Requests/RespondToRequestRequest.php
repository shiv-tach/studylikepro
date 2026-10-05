<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RespondToRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isTeacher() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', Rule::in(['accept', 'decline'])],
            'message' => ['nullable', 'string', 'max:1000'],
            'starts_at' => ['required_if:action,accept', 'nullable', 'date', 'after:now'],
        ];
    }
}
