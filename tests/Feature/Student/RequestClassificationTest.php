<?php

use App\Contracts\TopicClassifier;
use App\Enums\ClassificationStatus;
use App\Enums\RequestStatus;
use App\Jobs\ClassifyTutoringRequestJob;
use App\Models\Subject;
use App\Models\TeacherAvailabilitySlot;
use App\Models\TeacherProfile;
use App\Models\Topic;
use App\Models\TutoringRequest;
use App\Models\User;
use App\Notifications\RequestPublished;
use App\Services\RequestMatcher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

/**
 * @param  array<string, mixed>  $content
 */
function fakeOpenAiTopicResponse(array $content, int $status = 200): void
{
    Http::fake([
        '*/chat/completions' => Http::response([
            'id' => 'chatcmpl-test',
            'object' => 'chat.completion',
            'choices' => [[
                'index' => 0,
                'message' => ['role' => 'assistant', 'content' => json_encode($content)],
                'finish_reason' => 'stop',
            ]],
        ], $status),
    ]);
}

function runTopicClassification(TutoringRequest $request): void
{
    (new ClassifyTutoringRequestJob($request->id))->handle(
        app(TopicClassifier::class),
        app(RequestMatcher::class),
    );
}

function requestMatchingTeacher(Topic $topic, Subject $subject, int $weekday): TeacherProfile
{
    $profile = TeacherProfile::factory()->approved()->create([
        'user_id' => User::factory()->teacher()->create()->id,
        'timezone' => 'UTC',
    ]);

    $profile->subjects()->attach($subject->id, ['grade_levels' => ['high_school']]);
    $profile->topics()->attach($topic->id);
    TeacherAvailabilitySlot::factory()
        ->on($weekday, '18:00', '21:00')
        ->create(['teacher_profile_id' => $profile->id]);

    return $profile;
}

/**
 * An evening window two days out, expressed in UTC so it lines up with the
 * matching teacher's UTC availability.
 *
 * @return array{0: CarbonImmutable, 1: array<int, array{starts_at: string, ends_at: string}>}
 */
function classificationWindow(): array
{
    $start = CarbonImmutable::now('UTC')->addDays(2)->setTime(18, 0);

    return [$start, [[
        'starts_at' => $start->toIso8601String(),
        'ends_at' => $start->setTime(20, 0)->toIso8601String(),
    ]]];
}

beforeEach(function () {
    Notification::fake();

    $this->subject = Subject::factory()->create(['name' => 'Mathematics', 'slug' => 'mathematics']);
    $this->topic = Topic::factory()->create([
        'subject_id' => $this->subject->id,
        'name' => 'Algebra',
        'slug' => 'algebra',
    ]);
});

it('completes the request and notifies matching teachers when the model is confident', function () {
    fakeOpenAiTopicResponse(['subject' => 'Mathematics', 'topic' => 'Algebra', 'confidence' => 0.91, 'alternates' => []]);

    [$windowStart, $windows] = classificationWindow();

    $request = TutoringRequest::factory()->pendingClassification()->create([
        'subject_id' => null,
        'topic_id' => null,
        'preferred_windows' => $windows,
    ]);

    $teacher = requestMatchingTeacher($this->topic, $this->subject, $windowStart->dayOfWeek);

    runTopicClassification($request);
    $request->refresh();

    expect($request->classification_status)->toBe(ClassificationStatus::Completed)
        ->and($request->subject_id)->toBe($this->subject->id)
        ->and($request->topic_id)->toBe($this->topic->id)
        ->and($request->ai_confidence)->toBe(0.91);

    Notification::assertSentTo($teacher->user, RequestPublished::class);
});

it('asks the student to confirm when the confidence is below the threshold', function () {
    fakeOpenAiTopicResponse(['subject' => 'Mathematics', 'topic' => 'Algebra', 'confidence' => 0.4]);

    [$windowStart, $windows] = classificationWindow();

    $request = TutoringRequest::factory()->pendingClassification()->create([
        'subject_id' => null,
        'topic_id' => null,
        'preferred_windows' => $windows,
    ]);

    $teacher = requestMatchingTeacher($this->topic, $this->subject, $windowStart->dayOfWeek);

    runTopicClassification($request);
    $request->refresh();

    expect($request->classification_status)->toBe(ClassificationStatus::LowConfidence)
        ->and($request->subject_id)->toBeNull()
        ->and($request->topic_id)->toBeNull()
        ->and(data_get($request->ai_payload, 'topic_id'))->toBe($this->topic->id);

    Notification::assertNothingSentTo($teacher->user);
});

it('fails over to manual selection when the model answers with an unknown topic', function () {
    fakeOpenAiTopicResponse(['subject' => 'Mathematics', 'topic' => 'Rocket Propulsion', 'confidence' => 0.99]);

    $request = TutoringRequest::factory()->pendingClassification()->create([
        'subject_id' => null,
        'topic_id' => null,
    ]);

    runTopicClassification($request);
    $request->refresh();

    expect($request->classification_status)->toBe(ClassificationStatus::Failed)
        ->and($request->topic_id)->toBeNull()
        ->and($request->subject_id)->toBeNull()
        ->and(data_get($request->ai_payload, 'topic_id'))->toBeNull();
});

it('marks the request as failed when the provider returns an error', function () {
    Http::fake(['*/chat/completions' => Http::response(['error' => ['message' => 'boom']], 500)]);

    $request = TutoringRequest::factory()->pendingClassification()->create([
        'subject_id' => null,
        'topic_id' => null,
    ]);

    runTopicClassification($request);
    $request->refresh();

    expect($request->classification_status)->toBe(ClassificationStatus::Failed)
        ->and(data_get($request->ai_payload, 'error'))->not->toBeNull();
});

it('reuses the cached result for an identical image hash', function () {
    fakeOpenAiTopicResponse(['subject' => 'Mathematics', 'topic' => 'Algebra', 'confidence' => 0.88]);

    $first = TutoringRequest::factory()->pendingClassification()->create([
        'subject_id' => null,
        'topic_id' => null,
        'image_hash' => 'hash-abc-123',
    ]);

    $second = TutoringRequest::factory()->pendingClassification()->create([
        'subject_id' => null,
        'topic_id' => null,
        'image_hash' => 'hash-abc-123',
    ]);

    runTopicClassification($first);
    runTopicClassification($second);

    Http::assertSentCount(1);

    expect($second->fresh()->topic_id)->toBe($this->topic->id)
        ->and($second->fresh()->classification_status)->toBe(ClassificationStatus::Completed);
});

it('ignores requests that are no longer open', function () {
    fakeOpenAiTopicResponse(['subject' => 'Mathematics', 'topic' => 'Algebra', 'confidence' => 0.9]);

    $request = TutoringRequest::factory()->pendingClassification()->create([
        'subject_id' => null,
        'topic_id' => null,
        'status' => RequestStatus::Cancelled,
    ]);

    runTopicClassification($request);

    Http::assertNothingSent();

    expect($request->fresh()->classification_status)->toBe(ClassificationStatus::Pending);
});

it('publishes the request to matching teachers once the student confirms the topic', function () {
    [$windowStart, $windows] = classificationWindow();

    $student = User::factory()->student()->onboarded()->create();
    $request = TutoringRequest::factory()->create([
        'student_id' => $student->id,
        'subject_id' => null,
        'topic_id' => null,
        'classification_status' => ClassificationStatus::LowConfidence,
        'preferred_windows' => $windows,
    ]);

    $teacher = requestMatchingTeacher($this->topic, $this->subject, $windowStart->dayOfWeek);
    $unmatched = TeacherProfile::factory()->approved()->create(['timezone' => 'UTC']);

    $this->actingAs($student)
        ->put(route('student.requests.topic.update', $request), [
            'subject_id' => $this->subject->id,
            'topic_id' => $this->topic->id,
        ])
        ->assertRedirect(route('student.requests.show', $request));

    $request->refresh();

    expect($request->classification_status)->toBe(ClassificationStatus::Completed)
        ->and($request->subject_id)->toBe($this->subject->id)
        ->and($request->topic_id)->toBe($this->topic->id);

    Notification::assertSentToTimes($teacher->user, RequestPublished::class, 1);
    Notification::assertNothingSentTo($unmatched->user);
});

it('rejects a topic that does not belong to the chosen subject', function () {
    $otherSubject = Subject::factory()->create(['name' => 'Physics', 'slug' => 'physics']);

    $student = User::factory()->student()->onboarded()->create();
    $request = TutoringRequest::factory()->create([
        'student_id' => $student->id,
        'subject_id' => null,
        'topic_id' => null,
        'classification_status' => ClassificationStatus::LowConfidence,
    ]);

    $this->actingAs($student)
        ->put(route('student.requests.topic.update', $request), [
            'subject_id' => $otherSubject->id,
            'topic_id' => $this->topic->id,
        ])
        ->assertSessionHasErrors('topic_id');

    expect($request->fresh()->topic_id)->toBeNull();
});
