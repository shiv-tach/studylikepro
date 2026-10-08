<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class MarkPaperRequest extends FormRequest
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
        $maxFiles = (int) config('studylikepro.services.marking_max_files');

        return [
            'marked_files' => ['required', 'array', 'min:1', 'max:'.$maxFiles],
            'marked_files.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
            'feedback' => ['nullable', 'string', 'max:5000'],
            'marks' => ['nullable', 'array', 'max:50'],
            'marks.*.label' => ['nullable', 'string', 'max:120'],
            'marks.*.value' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * Normalised marks as key/value rows, skipping blank entries.
     *
     * @return list<array{label: string, value: string}>|null
     */
    public function markRows(): ?array
    {
        $marks = $this->validated('marks');

        if ($marks === null) {
            return null;
        }

        $rows = collect($marks)
            ->filter(fn (array $row) => filled($row['label'] ?? null) || filled($row['value'] ?? null))
            ->map(fn (array $row) => [
                'label' => (string) ($row['label'] ?? ''),
                'value' => (string) ($row['value'] ?? ''),
            ])
            ->values()
            ->all();

        return $rows === [] ? null : $rows;
    }
}
