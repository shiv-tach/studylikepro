<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRequestTopicRequest extends FormRequest
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
            'subject_id' => ['required', 'integer', Rule::exists('subjects', 'id')->where('is_active', true)],
            'topic_id' => [
                'required',
                'integer',
                Rule::exists('topics', 'id')
                    ->where('subject_id', $this->input('subject_id'))
                    ->where('is_active', true),
            ],
        ];
    }
}
