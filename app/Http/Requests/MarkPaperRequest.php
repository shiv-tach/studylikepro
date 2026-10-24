<?php

namespace App\Http\Requests;

use App\Support\MarkingAnnotationRules;
use Illuminate\Foundation\Http\FormRequest;

class MarkPaperRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isTeacher() ?? false;
    }

    /**
     * The studio posts `annotations` as one JSON string inside the multipart
     * form, so it has to be decoded before the nested rules can run.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('annotations'))) {
            $decoded = json_decode((string) $this->input('annotations'), true);
            $this->merge(['annotations' => is_array($decoded) ? $decoded : null]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $maxFiles = (int) config('studylikepro.services.marking_max_files');
        // A generated PDF is a fresh scan-like export, so it may be larger than
        // the 5 MB a student is allowed to upload.
        $maxKb = (int) config('studylikepro.services.marking_generated_max_kb', 15360);

        return [
            'marked_files' => ['required', 'array', 'min:1', 'max:'.$maxFiles],
            'marked_files.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:'.$maxKb],
            'feedback' => ['nullable', 'string', 'max:5000'],
            'marks' => ['nullable', 'array', 'max:50'],
            'marks.*.label' => ['nullable', 'string', 'max:120'],
            'marks.*.value' => ['nullable', 'string', 'max:120'],
            ...MarkingAnnotationRules::rubricScoreRules(),
            ...MarkingAnnotationRules::rules(),
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

    /**
     * @return list<array{key: string, score: float}>|null
     */
    public function rubricScores(): ?array
    {
        if (! $this->exists('rubric_scores')) {
            return null;
        }

        $rows = $this->input('rubric_scores');
        $scores = [];

        foreach (is_array($rows) ? $rows : [] as $row) {
            if (! is_array($row) || ! isset($row['key']) || ! is_numeric($row['score'] ?? null)) {
                continue;
            }

            $scores[] = [
                'key' => (string) $row['key'],
                'score' => (float) $row['score'],
            ];
        }

        return $scores === [] ? null : $scores;
    }

    /**
     * The final markup snapshot, if the client sent one.
     *
     * @return array<string, mixed>|null
     */
    public function annotationsPayload(): ?array
    {
        return MarkingAnnotationRules::normalize(
            is_array($this->input('annotations')) ? $this->input('annotations') : null,
        );
    }

    public function hasAnnotationPayload(): bool
    {
        return $this->exists('annotations');
    }
}
