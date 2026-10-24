<?php

namespace App\Http\Requests;

use App\Support\MarkingAnnotationRules;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The studio's autosave. It is deliberately partial: the client may send only
 * the annotations, only a rubric change or only the feedback draft, and the
 * keys it leaves out are preserved.
 */
class SaveMarkingDraftRequest extends FormRequest
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
            ...MarkingAnnotationRules::rules(),
            ...MarkingAnnotationRules::rubricScoreRules(),
            'feedback' => ['nullable', 'string', 'max:5000'],
        ];
    }

    /**
     * Only the keys the client actually sent, normalised for storage.
     *
     * @return array{annotations?: array<string, mixed>|null, rubric_scores?: list<array{key: string, score: float}>, feedback?: string|null}
     */
    public function changes(): array
    {
        $changes = [];

        if ($this->exists('annotations')) {
            $changes['annotations'] = MarkingAnnotationRules::normalize(
                is_array($this->input('annotations')) ? $this->input('annotations') : null,
            );
        }

        if ($this->exists('rubric_scores')) {
            $changes['rubric_scores'] = $this->normalizedScores();
        }

        if ($this->exists('feedback')) {
            $feedback = trim((string) $this->input('feedback'));
            $changes['feedback'] = $feedback === '' ? null : $feedback;
        }

        return $changes;
    }

    /**
     * @return list<array{key: string, score: float}>
     */
    private function normalizedScores(): array
    {
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

        return $scores;
    }
}
