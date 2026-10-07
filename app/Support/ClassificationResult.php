<?php

namespace App\Support;

use App\Models\Lesson;
use App\Models\Subject;

class ClassificationResult
{
    /**
     * @param  list<array{subject: string|null, lesson: string|null, confidence: float|null}>  $alternates
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly ?Subject $subject = null,
        public readonly ?Lesson $lesson = null,
        public readonly float $confidence = 0.0,
        public readonly array $alternates = [],
        public readonly array $raw = [],
        public readonly bool $failed = false,
    ) {}

    /**
     * @param  array<string, mixed>  $raw
     */
    public static function failed(array $raw = [], string $reason = ''): self
    {
        return new self(
            confidence: 0.0,
            raw: $reason !== '' ? [...$raw, 'error' => $reason] : $raw,
            failed: true,
        );
    }

    public function isConfident(): bool
    {
        return ! $this->failed
            && $this->subject !== null
            && $this->lesson !== null
            && $this->confidence >= platform_settings()->float('ai_min_confidence');
    }

    public function label(): string
    {
        if ($this->lesson === null) {
            return 'No lesson matched';
        }

        return $this->lesson->name.' · '.round($this->confidence * 100).'% confident';
    }

    /**
     * The payload persisted on the request for auditing and manual override pre-fills.
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array
    {
        return [
            'raw' => $this->raw,
            'subject_id' => $this->subject?->id,
            'lesson_id' => $this->lesson?->id,
            'confidence' => $this->confidence,
            'alternates' => $this->alternates,
        ];
    }
}
