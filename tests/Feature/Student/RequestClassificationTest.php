<?php

use App\Contracts\LessonClassifier;
use App\Enums\ClassificationStatus;
use App\Enums\RequestStatus;
use App\Jobs\ClassifyTutoringRequestJob;
use App\Models\EducationLevel;
use App\Models\Lesson;
use App\Models\Subject;
use App\Models\TeacherAvailabilitySlot;
use App\Models\TeacherProfile;
use App\Models\TutoringRequest;
use App\Models\User;
use App\Notifications\RequestPublished;
use App\Services\RequestMatcher;
use App\Support\ClassificationResult;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

/**
 * @param  array<string, mixed>  $content
 */
function fakeOpenAiLessonResponse(array $content, int $status = 200): void
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

function runLessonClassification(TutoringRequest $request): void
{
    (new ClassifyTutoringRequestJob($request->id))->handle(
        app(LessonClassifier::class),
        app(RequestMatcher::class),
    );
}

function requestMatchingTeacher(Lesson $lesson, Subject $subject, int $weekday): TeacherProfile
{
    $profile = TeacherProfile::factory()->approved()->create([
        'user_id' => User::factory()->teacher()->create()->id,
        'timezone' => 'UTC',
    ]);

    $profile->subjects()->attach($subject->id, ['grade_levels' => [(string) $lesson->grade_id]]);
    $profile->lessons()->attach($lesson->id);
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
    $this->lesson = Lesson::factory()->create([
        'subject_id' => $this->subject->id,
        'name' => 'Algebra',
        'slug' => 'algebra',
    ]);
});

it('completes the request and notifies matching teachers when the model is confident', function () {
    fakeOpenAiLessonResponse(['subject' => 'Mathematics', 'lesson' => 'Algebra', 'confidence' => 0.91, 'alternates' => []]);

    [$windowStart, $windows] = classificationWindow();

    $request = TutoringRequest::factory()->pendingClassification()->create([
        'subject_id' => null,
        'lesson_id' => null,
        'preferred_windows' => $windows,
    ]);

    $teacher = requestMatchingTeacher($this->lesson, $this->subject, $windowStart->dayOfWeek);

    runLessonClassification($request);
    $request->refresh();

    expect($request->classification_status)->toBe(ClassificationStatus::Completed)
        ->and($request->subject_id)->toBe($this->subject->id)
        ->and($request->lesson_id)->toBe($this->lesson->id)
        ->and($request->ai_confidence)->toBe(0.91);

    Notification::assertSentTo($teacher->user, RequestPublished::class);
});

it('asks the student to confirm when the confidence is below the threshold', function () {
    fakeOpenAiLessonResponse(['subject' => 'Mathematics', 'lesson' => 'Algebra', 'confidence' => 0.4]);

    [$windowStart, $windows] = classificationWindow();

    $request = TutoringRequest::factory()->pendingClassification()->create([
        'subject_id' => null,
        'lesson_id' => null,
        'preferred_windows' => $windows,
    ]);

    $teacher = requestMatchingTeacher($this->lesson, $this->subject, $windowStart->dayOfWeek);

    runLessonClassification($request);
    $request->refresh();

    expect($request->classification_status)->toBe(ClassificationStatus::LowConfidence)
        ->and($request->subject_id)->toBeNull()
        ->and($request->lesson_id)->toBeNull()
        ->and(data_get($request->ai_payload, 'lesson_id'))->toBe($this->lesson->id);

    Notification::assertNothingSentTo($teacher->user);
});

it('fails over to manual selection when the model answers with an unknown lesson', function () {
    fakeOpenAiLessonResponse(['subject' => 'Mathematics', 'lesson' => 'Rocket Propulsion', 'confidence' => 0.99]);

    $request = TutoringRequest::factory()->pendingClassification()->create([
        'subject_id' => null,
        'lesson_id' => null,
    ]);

    runLessonClassification($request);
    $request->refresh();

    expect($request->classification_status)->toBe(ClassificationStatus::Failed)
        ->and($request->lesson_id)->toBeNull()
        ->and($request->subject_id)->toBeNull()
        ->and(data_get($request->ai_payload, 'lesson_id'))->toBeNull();
});

it('marks the request as failed when the provider returns an error', function () {
    Http::fake(['*/chat/completions' => Http::response(['error' => ['message' => 'boom']], 500)]);

    $request = TutoringRequest::factory()->pendingClassification()->create([
        'subject_id' => null,
        'lesson_id' => null,
    ]);

    runLessonClassification($request);
    $request->refresh();

    expect($request->classification_status)->toBe(ClassificationStatus::Failed)
        ->and(data_get($request->ai_payload, 'error'))->not->toBeNull();
});

it('reuses the cached result for an identical image hash', function () {
    fakeOpenAiLessonResponse(['subject' => 'Mathematics', 'lesson' => 'Algebra', 'confidence' => 0.88]);

    $first = TutoringRequest::factory()->pendingClassification()->create([
        'subject_id' => null,
        'lesson_id' => null,
        'image_hash' => 'hash-abc-123',
    ]);

    $second = TutoringRequest::factory()->pendingClassification()->create([
        'subject_id' => null,
        'lesson_id' => null,
        'image_hash' => 'hash-abc-123',
    ]);

    runLessonClassification($first);
    runLessonClassification($second);

    Http::assertSentCount(1);

    expect($second->fresh()->lesson_id)->toBe($this->lesson->id)
        ->and($second->fresh()->classification_status)->toBe(ClassificationStatus::Completed);
});

it('ignores requests that are no longer open', function () {
    fakeOpenAiLessonResponse(['subject' => 'Mathematics', 'lesson' => 'Algebra', 'confidence' => 0.9]);

    $request = TutoringRequest::factory()->pendingClassification()->create([
        'subject_id' => null,
        'lesson_id' => null,
        'status' => RequestStatus::Cancelled,
    ]);

    runLessonClassification($request);

    Http::assertNothingSent();

    expect($request->fresh()->classification_status)->toBe(ClassificationStatus::Pending);
});

it('publishes the request to matching teachers once the student confirms the lesson', function () {
    [$windowStart, $windows] = classificationWindow();

    $student = User::factory()->student()->onboarded()->create();
    $request = TutoringRequest::factory()->create([
        'student_id' => $student->id,
        'subject_id' => null,
        'lesson_id' => null,
        'classification_status' => ClassificationStatus::LowConfidence,
        'preferred_windows' => $windows,
    ]);

    $teacher = requestMatchingTeacher($this->lesson, $this->subject, $windowStart->dayOfWeek);
    $unmatched = TeacherProfile::factory()->approved()->create(['timezone' => 'UTC']);

    $this->actingAs($student)
        ->put(route('student.requests.lesson.update', $request), [
            'subject_id' => $this->subject->id,
            'lesson_id' => $this->lesson->id,
        ])
        ->assertRedirect(route('student.requests.show', $request));

    $request->refresh();

    expect($request->classification_status)->toBe(ClassificationStatus::Completed)
        ->and($request->subject_id)->toBe($this->subject->id)
        ->and($request->lesson_id)->toBe($this->lesson->id);

    Notification::assertSentToTimes($teacher->user, RequestPublished::class, 1);
    Notification::assertNothingSentTo($unmatched->user);
});

it('rejects a lesson that does not belong to the chosen subject', function () {
    $otherSubject = Subject::factory()->create(['name' => 'Physics', 'slug' => 'physics']);

    $student = User::factory()->student()->onboarded()->create();
    $request = TutoringRequest::factory()->create([
        'student_id' => $student->id,
        'subject_id' => null,
        'lesson_id' => null,
        'classification_status' => ClassificationStatus::LowConfidence,
    ]);

    $this->actingAs($student)
        ->put(route('student.requests.lesson.update', $request), [
            'subject_id' => $otherSubject->id,
            'lesson_id' => $this->lesson->id,
        ])
        ->assertSessionHasErrors('lesson_id');

    expect($request->fresh()->lesson_id)->toBeNull();
});

it('offers the model only the lessons of the student grade', function () {
    $olMaths = Subject::factory()->create([
        'name' => 'Mathematics',
        'slug' => 'ol-mathematics',
        'education_level_id' => EducationLevel::query()->where('key', 'ol')->value('id'),
    ]);

    $gradeSix = Lesson::factory()->create([
        'subject_id' => $olMaths->id,
        'grade_id' => gradeId(6),
        'name' => 'Prime Numbers',
        'slug' => 'prime-numbers',
    ]);

    Lesson::factory()->create([
        'subject_id' => $olMaths->id,
        'grade_id' => gradeId(7),
        'name' => 'Fractions',
        'slug' => 'fractions',
    ]);

    fakeOpenAiLessonResponse(['subject' => 'Mathematics', 'lesson' => 'Prime Numbers', 'confidence' => 0.9]);

    $request = TutoringRequest::factory()->pendingClassification()->create([
        'subject_id' => null,
        'lesson_id' => null,
        'grade_id' => gradeId(6),
    ]);

    runLessonClassification($request);
    $request->refresh();

    Http::assertSent(function ($sent) {
        $prompt = (string) data_get($sent->data(), 'messages.0.content');

        return str_contains($prompt, 'Prime Numbers') && ! str_contains($prompt, 'Fractions');
    });

    expect($request->classification_status)->toBe(ClassificationStatus::Completed)
        ->and($request->lesson_id)->toBe($gradeSix->id)
        ->and($request->grade_id)->toBe(gradeId(6));
});

it('refuses a lesson the model places outside the student grade', function () {
    Lesson::factory()->create([
        'subject_id' => $this->subject->id,
        'grade_id' => gradeId(6),
        'name' => 'Prime Numbers',
        'slug' => 'prime-numbers',
    ]);

    Lesson::factory()->create([
        'subject_id' => $this->subject->id,
        'grade_id' => gradeId(7),
        'name' => 'Fractions',
        'slug' => 'fractions',
    ]);

    fakeOpenAiLessonResponse(['subject' => 'Mathematics', 'lesson' => 'Fractions', 'confidence' => 0.97]);

    $request = TutoringRequest::factory()->pendingClassification()->create([
        'subject_id' => null,
        'lesson_id' => null,
        'grade_id' => gradeId(6),
    ]);

    runLessonClassification($request);
    $request->refresh();

    expect($request->classification_status)->toBe(ClassificationStatus::Failed)
        ->and($request->lesson_id)->toBeNull()
        ->and($request->subject_id)->toBeNull();
});

it('keeps the cached classification per grade for the same image', function () {
    $olMaths = Subject::factory()->create([
        'name' => 'Mathematics',
        'slug' => 'ol-mathematics',
        'education_level_id' => EducationLevel::query()->where('key', 'ol')->value('id'),
    ]);

    $gradeSix = Lesson::factory()->create([
        'subject_id' => $olMaths->id,
        'grade_id' => gradeId(6),
        'name' => 'Prime Numbers',
        'slug' => 'prime-numbers',
    ]);

    $gradeSeven = Lesson::factory()->create([
        'subject_id' => $olMaths->id,
        'grade_id' => gradeId(7),
        'name' => 'Fractions',
        'slug' => 'fractions',
    ]);

    Http::fake(function ($request) {
        $prompt = (string) data_get($request->data(), 'messages.0.content');
        $lesson = str_contains($prompt, 'Prime Numbers') ? 'Prime Numbers' : 'Fractions';

        return Http::response([
            'choices' => [[
                'message' => [
                    'role' => 'assistant',
                    'content' => json_encode(['subject' => 'Mathematics', 'lesson' => $lesson, 'confidence' => 0.9]),
                ],
            ]],
        ]);
    });

    $six = TutoringRequest::factory()->pendingClassification()->create([
        'subject_id' => null,
        'lesson_id' => null,
        'grade_id' => gradeId(6),
        'image_hash' => 'hash-grade-scoped',
    ]);

    $seven = TutoringRequest::factory()->pendingClassification()->create([
        'subject_id' => null,
        'lesson_id' => null,
        'grade_id' => gradeId(7),
        'image_hash' => 'hash-grade-scoped',
    ]);

    runLessonClassification($six);
    runLessonClassification($seven);

    Http::assertSentCount(2);

    expect($six->fresh()->lesson_id)->toBe($gradeSix->id)
        ->and($seven->fresh()->lesson_id)->toBe($gradeSeven->id);
});

it('never publishes a lesson the classifier places outside the student grade', function () {
    Lesson::factory()->create([
        'subject_id' => $this->subject->id,
        'grade_id' => gradeId(6),
        'name' => 'Prime Numbers',
        'slug' => 'prime-numbers',
    ]);

    $gradeSeven = Lesson::factory()->create([
        'subject_id' => $this->subject->id,
        'grade_id' => gradeId(7),
        'name' => 'Fractions',
        'slug' => 'fractions',
    ]);

    $classifier = new class($gradeSeven) implements LessonClassifier
    {
        public function __construct(private readonly Lesson $lesson) {}

        public function classify(TutoringRequest $request): ClassificationResult
        {
            return new ClassificationResult(
                subject: $this->lesson->subject,
                lesson: $this->lesson,
                confidence: 0.99,
            );
        }
    };

    app()->instance(LessonClassifier::class, $classifier);

    $request = TutoringRequest::factory()->pendingClassification()->create([
        'subject_id' => null,
        'lesson_id' => null,
        'grade_id' => gradeId(6),
    ]);

    runLessonClassification($request);
    $request->refresh();

    expect($request->classification_status)->toBe(ClassificationStatus::LowConfidence)
        ->and($request->lesson_id)->toBeNull()
        ->and($request->subject_id)->toBeNull();
});
