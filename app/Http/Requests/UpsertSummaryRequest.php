<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpsertSummaryRequest extends FormRequest
{
    /**
     * Authorization happens in the LessonSummaryPolicy; this request only
     * carries the shape of the content the teacher may save.
     */
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
            'content' => ['required', 'string', 'min:20', 'max:20000'],
        ];
    }
}
