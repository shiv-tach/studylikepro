<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SubmitPaperRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isStudent() ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxFiles = (int) config('studylikepro.services.marking_max_files');

        return [
            'files' => ['required', 'array', 'min:1', 'max:'.$maxFiles],
            'files.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'page_count' => ['nullable', 'integer', 'min:1', 'max:500'],
        ];
    }
}
