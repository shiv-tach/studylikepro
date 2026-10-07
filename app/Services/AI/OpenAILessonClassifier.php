<?php

namespace App\Services\AI;

use App\Contracts\LessonClassifier;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\RequestAttachment;
use App\Models\Subject;
use App\Models\TutoringRequest;
use App\Support\ClassificationResult;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * OpenAI chat-completions adapter that maps the model's answer onto the catalog.
 */
class OpenAILessonClassifier implements LessonClassifier
{
    public function classify(TutoringRequest $request): ClassificationResult
    {
        $response = Http::withToken((string) config('services.openai.key'))
            ->acceptJson()
            ->timeout((int) config('services.openai.timeout'))
            ->post($this->endpoint(), [
                'model' => config('services.openai.model'),
                'temperature' => 0,
                'response_format' => ['type' => 'json_object'],
                'messages' => [
                    ['role' => 'system', 'content' => $this->systemPrompt($request)],
                    ['role' => 'user', 'content' => $this->userContent($request)],
                ],
            ]);

        $response->throw();

        $payload = $response->json() ?? [];
        $content = data_get($payload, 'choices.0.message.content');

        if (! is_string($content)) {
            return ClassificationResult::failed(['response' => $payload], 'The model returned no content.');
        }

        $decoded = json_decode($content, true);

        if (! is_array($decoded)) {
            return ClassificationResult::failed(['content' => $content], 'The model returned invalid JSON.');
        }

        $grade = $request->grade;

        return $this->map($decoded, $payload, $grade);
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @param  array<string, mixed>  $payload
     */
    private function map(array $decoded, array $payload, ?Grade $grade): ClassificationResult
    {
        $confidence = (float) ($decoded['confidence'] ?? 0);
        if ($confidence > 1) {
            $confidence /= 100;
        }

        $subject = $this->matchSubject($decoded['subject'] ?? null, $grade);
        $lesson = $this->matchLesson($decoded['lesson'] ?? null, $subject, $grade);

        if ($subject === null && $lesson !== null) {
            $subject = $lesson->subject;
        }

        $alternates = collect($decoded['alternates'] ?? [])
            ->filter(fn ($alternate) => is_array($alternate))
            ->map(fn (array $alternate) => [
                'subject' => $alternate['subject'] ?? null,
                'lesson' => $alternate['lesson'] ?? null,
                'confidence' => isset($alternate['confidence']) ? (float) $alternate['confidence'] : null,
            ])
            ->values()
            ->all();

        return new ClassificationResult(
            subject: $subject,
            lesson: $lesson,
            confidence: max(0.0, min(1.0, $confidence)),
            alternates: $alternates,
            raw: ['response' => $payload, 'parsed' => $decoded],
            failed: $subject === null || $lesson === null,
        );
    }

    private function matchSubject(?string $name, ?Grade $grade): ?Subject
    {
        if (blank($name)) {
            return null;
        }

        /** @var Collection<int, Subject> $subjects */
        $subjects = Subject::query()
            ->where('is_active', true)
            ->when($grade !== null, fn ($query) => $query->where('education_level_id', $grade->education_level_id))
            ->get();

        return $this->bestMatch($subjects, $name);
    }

    private function matchLesson(?string $name, ?Subject $subject, ?Grade $grade): ?Lesson
    {
        if (blank($name)) {
            return null;
        }

        $query = Lesson::query()
            ->where('is_active', true)
            ->when($grade !== null, fn ($query) => $query->where('grade_id', $grade->id));

        if ($subject) {
            $query->where('subject_id', $subject->id);
        }

        $lessons = $query->get();

        // A subject name without its lesson can still resolve inside the grade.
        if ($lessons->isEmpty() && $subject) {
            $lessons = Lesson::query()
                ->where('is_active', true)
                ->when($grade !== null, fn ($query) => $query->where('grade_id', $grade->id))
                ->get();
        }

        return $this->bestMatch($lessons, $name);
    }

    /**
     * @param  Collection<int, Model>  $candidates
     */
    private function bestMatch(Collection $candidates, string $name): ?Model
    {
        $needle = Str::lower(trim($name));

        $exact = $candidates->first(fn (Model $model) => Str::lower($model->name) === $needle);
        if ($exact) {
            return $exact;
        }

        $slug = Str::slug($name);
        $bySlug = $candidates->first(fn (Model $model) => $model->slug === $slug);
        if ($bySlug) {
            return $bySlug;
        }

        return $candidates->first(fn (Model $model) => $this->similarity(Str::lower($model->name), $needle) >= 70);
    }

    private function similarity(string $a, string $b): float
    {
        $percent = 0.0;
        similar_text($a, $b, $percent);

        return $percent;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function userContent(TutoringRequest $request): array
    {
        $content = [
            ['type' => 'text', 'text' => $request->description],
        ];

        foreach ($request->attachments as $attachment) {
            if (! $attachment->isImage()) {
                continue;
            }

            $content[] = [
                'type' => 'image_url',
                'image_url' => ['url' => $this->dataUrl($attachment)],
            ];
        }

        return $content;
    }

    private function dataUrl(RequestAttachment $attachment): string
    {
        $contents = Storage::disk('local')->get($attachment->path);

        return 'data:'.$attachment->mime_type.';base64,'.base64_encode((string) $contents);
    }

    private function systemPrompt(TutoringRequest $request): string
    {
        $grade = $request->grade;

        $catalog = Subject::query()
            ->active()
            ->ordered()
            ->when($grade !== null, fn ($query) => $query->where('education_level_id', $grade->education_level_id))
            ->with(['lessons' => fn ($query) => $query->active()
                ->ordered()
                ->when($grade !== null, fn ($lessons) => $lessons->where('grade_id', $grade->id))])
            ->get()
            ->map(fn (Subject $subject) => $subject->name.': '.$subject->lessons->pluck('name')->implode(', '))
            ->implode("\n");

        $scope = $grade !== null
            ? "\n\nThe student is in {$grade->label}. Choose only from the lessons listed above for that grade."
            : '';

        return <<<PROMPT
        You classify school and college tutoring questions so we can match the student with a verified teacher.

        Choose exactly one subject and one lesson from this catalog:
        {$catalog}{$scope}

        Answer with JSON only:
        {"subject": "<catalog subject>", "lesson": "<catalog lesson>", "confidence": <0 to 1>, "alternates": [{"subject": "<catalog subject>", "lesson": "<catalog lesson>", "confidence": <0 to 1>}]}

        Use the catalog spelling. If the question is unclear, lower the confidence instead of inventing a lesson.
        PROMPT;
    }

    private function endpoint(): string
    {
        return rtrim((string) config('services.openai.base_url'), '/').'/chat/completions';
    }
}
