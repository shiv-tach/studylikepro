<?php

use App\Enums\ClassificationStatus;
use App\Enums\RequestStatus;
use App\Models\Lesson;
use App\Models\RequestResponse;
use App\Models\Subject;
use App\Models\TutoringRequest;
use App\Models\User;
use Illuminate\Testing\TestResponse;

/**
 * The request list is rendered by resources/js/student-requests, so the Blade
 * view only has to hand the Vue app a correct config island. These tests pin
 * that contract down: the cards, the buckets, the copy and the route map.
 */
function requestsConfig(TestResponse $response): array
{
    preg_match(
        '#<script type="application/json" id="student-requests-config">(.*?)</script>#s',
        $response->getContent(),
        $matches
    );

    return json_decode($matches[1] ?? '[]', true, 512, JSON_THROW_ON_ERROR);
}

test('the request island carries the cards, the buckets and the route map', function () {
    $student = User::factory()->student()->onboarded()->create();

    $subject = Subject::factory()->create(['name' => 'Combined Mathematics']);
    $lesson = Lesson::factory()->for($subject)->create(['name' => 'Quadratic factorising']);

    $open = TutoringRequest::factory()->create([
        'student_id' => $student->id,
        'subject_id' => $subject->id,
        'lesson_id' => $lesson->id,
        'description' => 'I keep losing marks on quadratic factorising.',
    ]);

    RequestResponse::factory()->create(['tutoring_request_id' => $open->id]);

    $matched = TutoringRequest::factory()->create([
        'student_id' => $student->id,
        'status' => RequestStatus::Matched,
    ]);

    $closed = TutoringRequest::factory()->create([
        'student_id' => $student->id,
        'status' => RequestStatus::Cancelled,
    ]);

    $config = requestsConfig(
        $this->actingAs($student)->get(route('student.requests.index'))->assertOk()
    );

    expect($config['urls']['create'])->toBe(route('student.requests.create'));
    expect($config['theme']['preset'])->toBe('classic');
    expect(collect($config['requests'])->pluck('id')->all())->toEqualCanonicalizing([
        $open->id,
        $matched->id,
        $closed->id,
    ]);

    // Every bucket counts the student's whole history, not just this page.
    expect(collect($config['filters'])->pluck('count', 'key')->all())->toBe([
        'all' => 3,
        'open' => 1,
        'matched' => 1,
        'closed' => 1,
    ]);
    expect(collect($config['stats'])->pluck('value', 'key')->all())->toBe([
        'open' => 1,
        'matched' => 1,
        'total' => 3,
    ]);
});

test('a matched request card carries its lesson, subject, proposals and windows', function () {
    $student = User::factory()->student()->onboarded()->create();

    $subject = Subject::factory()->create(['name' => 'Combined Mathematics', 'icon' => '📐']);
    $lesson = Lesson::factory()->for($subject)->create(['name' => 'Quadratic factorising']);

    $request = TutoringRequest::factory()->create([
        'student_id' => $student->id,
        'subject_id' => $subject->id,
        'lesson_id' => $lesson->id,
        'description' => 'I keep losing marks on quadratic factorising.',
        'status' => RequestStatus::Matched,
    ]);

    RequestResponse::factory()->create(['tutoring_request_id' => $request->id]);

    $config = requestsConfig(
        $this->actingAs($student)->get(route('student.requests.index'))->assertOk()
    );

    $card = collect($config['requests'])->firstWhere('id', $request->id);

    expect($card['url'])->toBe(route('student.requests.show', $request));
    expect($card['statusLabel'])->toBe(RequestStatus::Matched->label());
    expect($card['group'])->toBe('matched');
    expect($card['title'])->toBe('Quadratic factorising');
    expect($card['lessonName'])->toBe('Quadratic factorising');
    // The heading already names the lesson, so the card repeats the question in
    // the body instead of printing the same sentence twice.
    expect($card['showDescription'])->toBeTrue();
    expect($card['subject'])->toBe(['icon' => '📐', 'name' => 'Combined Mathematics']);
    expect($card['proposals']['count'])->toBe(1);
    expect($card['proposals']['label'])->toBe('1 teacher proposal');
    expect($card['classification'])->toBeNull();
    expect($card['windows'])->toHaveCount(1);
    expect($card['windows'][0])->toMatch('/^[A-Z][a-z]{2} \d{2} [A-Z][a-z]{2} · \d{2}:\d{2}–\d{2}:\d{2}$/');
});

test('a request with no lesson yet falls back to its question and names the AI state', function () {
    $student = User::factory()->student()->onboarded()->create();

    $request = TutoringRequest::factory()->pendingClassification()->create([
        'student_id' => $student->id,
        'description' => 'Integration by parts keeps tripping me up.',
    ]);

    $config = requestsConfig(
        $this->actingAs($student)->get(route('student.requests.index'))->assertOk()
    );

    $card = collect($config['requests'])->firstWhere('id', $request->id);

    expect($card['group'])->toBe('open');
    expect($card['lessonName'])->toBeNull();
    expect($card['title'])->toBe('Integration by parts keeps tripping me up.');
    expect($card['showDescription'])->toBeFalse();
    expect($card['subject'])->toBeNull();
    expect($card['proposals'])->toBeNull();
    expect($card['classification'])->toBe([
        'status' => ClassificationStatus::Pending->value,
        'label' => ClassificationStatus::Pending->label(),
    ]);
    expect($card['expiresLabel'])->toStartWith('Open until');
});

test('the cancelled flash only arrives after a cancellation', function () {
    $student = User::factory()->student()->onboarded()->create();
    $request = TutoringRequest::factory()->create(['student_id' => $student->id]);

    expect(requestsConfig(
        $this->actingAs($student)->get(route('student.requests.index'))->assertOk()
    )['flash']['cancelled'])->toBeFalse();

    $this->actingAs($student)
        ->post(route('student.requests.cancel', $request))
        ->assertRedirect(route('student.requests.index'));

    expect(requestsConfig(
        $this->actingAs($student)->get(route('student.requests.index'))->assertOk()
    )['flash']['cancelled'])->toBeTrue();
});
