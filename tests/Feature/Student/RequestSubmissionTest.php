<?php

use App\Enums\ClassificationStatus;
use App\Enums\RequestStatus;
use App\Enums\ResponseStatus;
use App\Jobs\ClassifyTutoringRequestJob;
use App\Models\Booking;
use App\Models\Grade;
use App\Models\RequestResponse;
use App\Models\TutoringRequest;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function studentRequestPayload(array $overrides = []): array
{
    return array_merge([
        'description' => 'I keep losing marks on quadratic factorising and my board exam is next month.',
        'windows' => [[
            'date' => now()->addDay()->toDateString(),
            'from' => '18:00',
            'to' => '20:00',
        ]],
    ], $overrides);
}

it('creates an open request, converts windows to utc, and queues classification', function () {
    Queue::fake();

    $student = User::factory()->student()->onboarded()->create();

    $this->actingAs($student)
        ->post(route('student.requests.store'), studentRequestPayload(['budget' => '750']))
        ->assertRedirect();

    $request = TutoringRequest::query()->firstOrFail();

    expect($request->student_id)->toBe($student->id)
        ->and($request->budget_minor)->toBe(75000)
        ->and($request->status)->toBe(RequestStatus::Open)
        ->and($request->classification_status)->toBe(ClassificationStatus::Pending)
        ->and($request->expires_at)->not->toBeNull();

    [$start, $end] = $request->windows()[0];

    // 18:00–20:00 in the student's Asia/Colombo timezone is 12:30–14:30 UTC.
    expect($start->format('H:i'))->toBe('12:30')
        ->and($end->format('H:i'))->toBe('14:30');

    Queue::assertPushed(
        ClassifyTutoringRequestJob::class,
        fn (ClassifyTutoringRequestJob $job) => $job->tutoringRequestId === $request->id,
    );
});

it('stores question images privately and hashes the first one', function () {
    Queue::fake();
    Storage::fake('local');

    $student = User::factory()->student()->onboarded()->create();

    $this->actingAs($student)
        ->post(route('student.requests.store'), studentRequestPayload([
            'attachments' => [UploadedFile::fake()->image('question.png', 400, 400)],
        ]))
        ->assertRedirect();

    $request = TutoringRequest::query()->with('attachments')->firstOrFail();
    $attachment = $request->attachments->first();

    expect($attachment->original_name)->toBe('question.png')
        ->and($attachment->mime_type)->toStartWith('image/')
        ->and($request->image_hash)->toBe(hash('sha256', Storage::disk('local')->get($attachment->path)));

    Storage::disk('local')->assertExists($attachment->path);
    Storage::disk('public')->assertMissing($attachment->path);
});

it('rejects non-image attachments and too many attachments', function () {
    Queue::fake();
    Storage::fake('local');

    $student = User::factory()->student()->onboarded()->create();

    $this->actingAs($student)
        ->post(route('student.requests.store'), studentRequestPayload([
            'attachments' => [UploadedFile::fake()->create('notes.pdf', 120, 'application/pdf')],
        ]))
        ->assertSessionHasErrors('attachments.0');

    $this->actingAs($student)
        ->post(route('student.requests.store'), studentRequestPayload([
            'attachments' => collect(range(1, 4))
                ->map(fn (int $index) => UploadedFile::fake()->image("question-{$index}.png"))
                ->all(),
        ]))
        ->assertSessionHasErrors('attachments');

    expect(TutoringRequest::query()->count())->toBe(0);
});

it('validates the question text and preferred windows', function () {
    Queue::fake();

    $student = User::factory()->student()->onboarded()->create();

    $this->actingAs($student)
        ->post(route('student.requests.store'), ['description' => 'Too short', 'windows' => []])
        ->assertSessionHasErrors(['description', 'windows']);

    $this->actingAs($student)
        ->post(route('student.requests.store'), studentRequestPayload([
            'windows' => [['date' => now()->addDay()->toDateString(), 'from' => '20:00', 'to' => '18:00']],
        ]))
        ->assertSessionHasErrors('windows.0.to');

    $this->actingAs($student)
        ->post(route('student.requests.store'), studentRequestPayload([
            'windows' => [['date' => now()->subDay()->toDateString(), 'from' => '18:00', 'to' => '20:00']],
        ]))
        ->assertSessionHasErrors('windows.0.date');

    expect(TutoringRequest::query()->count())->toBe(0);
});

it('enforces the daily submission limit', function () {
    Queue::fake();
    config(['studylikepro.requests.daily_submission_limit' => 1]);

    $student = User::factory()->student()->onboarded()->create();
    TutoringRequest::factory()->create(['student_id' => $student->id]);

    $this->actingAs($student)
        ->post(route('student.requests.store'), studentRequestPayload())
        ->assertSessionHasErrors('description');

    expect(TutoringRequest::query()->count())->toBe(1);
});

it('shows students only their own requests', function () {
    $student = User::factory()->student()->onboarded()->create();

    $mine = TutoringRequest::factory()->create([
        'student_id' => $student->id,
        'description' => 'Factorising quadratics is costing me marks.',
    ]);

    TutoringRequest::factory()->create(['description' => 'Unrelated chemistry question about moles.']);

    $this->actingAs($student)
        ->get(route('student.requests.index'))
        ->assertOk()
        ->assertSee('Factorising quadratics is costing me marks.')
        ->assertDontSee('Unrelated chemistry question about moles.');

    $this->actingAs($student)->get(route('student.requests.show', $mine))->assertOk();

    $this->actingAs($student)
        ->get(route('student.requests.show', TutoringRequest::factory()->create()))
        ->assertForbidden();
});

it('shows proposals and the payment hold on the request page', function () {
    $student = User::factory()->student()->onboarded()->create();
    $request = TutoringRequest::factory()->create([
        'student_id' => $student->id,
        'status' => RequestStatus::Matched,
    ]);

    RequestResponse::factory()->create([
        'tutoring_request_id' => $request->id,
        'message' => 'Bring your latest test paper to the first lesson.',
    ]);

    Booking::factory()->hold()->create([
        'student_id' => $student->id,
        'tutoring_request_id' => $request->id,
        'teacher_profile_id' => $request->responses()->first()->teacher_profile_id,
    ]);

    $this->actingAs($student)
        ->get(route('student.requests.show', $request))
        ->assertOk()
        ->assertSee('Bring your latest test paper to the first lesson.')
        ->assertSee('Your teacher is holding this slot')
        ->assertSee('Awaiting payment');
});

it('cancels an open request and expires pending proposals', function () {
    $student = User::factory()->student()->onboarded()->create();
    $request = TutoringRequest::factory()->create(['student_id' => $student->id]);
    $response = RequestResponse::factory()->create(['tutoring_request_id' => $request->id]);

    $this->actingAs($student)
        ->post(route('student.requests.cancel', $request))
        ->assertRedirect(route('student.requests.index'));

    expect($request->fresh()->status)->toBe(RequestStatus::Cancelled)
        ->and($request->fresh()->cancelled_at)->not->toBeNull()
        ->and($response->fresh()->status)->toBe(ResponseStatus::Expired);
});

it('keeps teachers and guests out of the student request routes', function () {
    $this->get(route('student.requests.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->teacher()->onboarded()->create())
        ->get(route('student.requests.index'))
        ->assertForbidden();
});

it('locks the request grade to the learning profile', function () {
    Queue::fake();

    $student = User::factory()->student()->onboarded()->create();
    $profileGrade = $student->studentProfile->grade;
    $otherGrade = Grade::factory()->create([
        'education_level_id' => $profileGrade->education_level_id,
    ]);

    $this->actingAs($student)
        ->get(route('student.requests.create'))
        ->assertOk()
        ->assertSee($profileGrade->label)
        ->assertDontSee('name="grade_id"', false);

    $this->actingAs($student)
        ->post(route('student.requests.store'), studentRequestPayload(['grade_id' => $otherGrade->id]))
        ->assertRedirect();

    expect(TutoringRequest::query()->latest('id')->firstOrFail()->grade_id)->toBe($profileGrade->id);
});

it('shows the pending state while the classification is running', function () {
    $student = User::factory()->student()->onboarded()->create();
    $request = TutoringRequest::factory()->pendingClassification()->create([
        'student_id' => $student->id,
        'subject_id' => null,
        'lesson_id' => null,
    ]);

    $this->actingAs($student)
        ->get(route('student.requests.show', $request))
        ->assertOk()
        ->assertSee('AI lesson matching')
        ->assertSee('Reading your question')
        ->assertSee('Proposals appear here as soon as the lesson is confirmed.')
        ->assertDontSee('Confirm lesson');
});
