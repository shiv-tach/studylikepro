<?php

namespace App\Jobs;

use App\Contracts\TopicClassifier;
use App\Enums\ClassificationStatus;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\TutoringRequest;
use App\Notifications\RequestPublished;
use App\Services\RequestMatcher;
use App\Support\ClassificationResult;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Asks the classifier for a subject/topic and publishes the request to matching teachers.
 */
class ClassifyTutoringRequestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public function __construct(public readonly int $tutoringRequestId) {}

    public function handle(TopicClassifier $classifier, RequestMatcher $matcher): void
    {
        $request = TutoringRequest::query()->with('attachments')->find($this->tutoringRequestId);

        if (! $request || ! $request->isOpen()) {
            return;
        }

        $cacheKey = $request->image_hash ? "ai:classification:{$request->image_hash}" : null;
        $cached = $cacheKey ? Cache::get($cacheKey) : null;

        if (is_array($cached)) {
            $this->apply($request, new ClassificationResult(
                subject: Subject::find($cached['subject_id'] ?? null),
                topic: Topic::find($cached['topic_id'] ?? null),
                confidence: (float) ($cached['confidence'] ?? 0),
                raw: ['cached' => true],
            ), $matcher);

            return;
        }

        try {
            $result = $classifier->classify($request);
        } catch (Throwable $exception) {
            report($exception);

            $request->update([
                'classification_status' => ClassificationStatus::Failed,
                'ai_payload' => ['error' => $exception->getMessage()],
            ]);

            return;
        }

        if ($cacheKey && ! $result->failed && $result->topic && $result->subject) {
            Cache::put($cacheKey, [
                'subject_id' => $result->subject->id,
                'topic_id' => $result->topic->id,
                'confidence' => $result->confidence,
            ], now()->addMinutes((int) config('studylikepro.ai.cache_ttl_minutes')));
        }

        $this->apply($request, $result, $matcher);
    }

    private function apply(TutoringRequest $request, ClassificationResult $result, RequestMatcher $matcher): void
    {
        $confident = $result->isConfident();

        $request->update([
            'classification_status' => match (true) {
                $result->failed => ClassificationStatus::Failed,
                $confident => ClassificationStatus::Completed,
                default => ClassificationStatus::LowConfidence,
            },
            'ai_confidence' => $result->confidence,
            'ai_payload' => $result->toPayload(),
            'subject_id' => $confident ? $result->subject->id : null,
            'topic_id' => $confident ? $result->topic->id : null,
        ]);

        if (! $confident) {
            return;
        }

        foreach ($matcher->teachersFor($request->fresh()) as $teacher) {
            $teacher->user->notify(new RequestPublished($request));
        }
    }
}
