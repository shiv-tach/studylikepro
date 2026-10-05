<?php

use App\Enums\BookingStatus;
use App\Enums\RequestStatus;
use App\Enums\ResponseStatus;
use App\Models\Booking;
use App\Models\RequestResponse;
use App\Models\Subject;
use App\Models\TeacherAvailabilitySlot;
use App\Models\TeacherProfile;
use App\Models\Topic;
use App\Models\TutoringRequest;
use App\Models\User;
use App\Notifications\RequestResponseAccepted;
use App\Notifications\RequestResponseDeclined;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

/**
 * A verified maths teacher who teaches algebra on the evening two days out,
 * and a student request inside that evening.
 *
 * @return array<string, mixed>
 */
function teacherInboxScenario(): array
{
    $subject = Subject::factory()->create(['name' => 'Mathematics', 'slug' => 'mathematics']);
    $topic = Topic::factory()->create([
        'subject_id' => $subject->id,
        'name' => 'Algebra',
        'slug' => 'algebra',
    ]);

    $teacherUser = User::factory()->teacher()->create();
    $teacher = TeacherProfile::factory()->approved()->create([
        'user_id' => $teacherUser->id,
        'timezone' => 'UTC',
        'hourly_rate_minor' => 60000,
        'lesson_duration_minutes' => 60,
    ]);
    $teacher->subjects()->attach($subject->id, ['grade_levels' => ['high_school']]);
    $teacher->topics()->attach($topic->id);

    $windowStart = CarbonImmutable::now('UTC')->addDays(2)->setTime(18, 0);
    TeacherAvailabilitySlot::factory()
        ->on($windowStart->dayOfWeek, '18:00', '21:00')
        ->create(['teacher_profile_id' => $teacher->id]);

    $student = User::factory()->student()->onboarded()->create();
    $request = TutoringRequest::factory()->create([
        'student_id' => $student->id,
        'subject_id' => $subject->id,
        'topic_id' => $topic->id,
        'preferred_windows' => [[
            'starts_at' => $windowStart->toIso8601String(),
            'ends_at' => $windowStart->setTime(20, 0)->toIso8601String(),
        ]],
    ]);

    return compact('subject', 'topic', 'teacher', 'teacherUser', 'student', 'request', 'windowStart');
}

it('shows only requests that match the teacher topics and availability', function () {
    $scenario = teacherInboxScenario();

    $otherTopic = Topic::factory()->create([
        'subject_id' => $scenario['subject']->id,
        'name' => 'Geometry',
        'slug' => 'geometry',
    ]);

    TutoringRequest::factory()->create([
        'subject_id' => $scenario['subject']->id,
        'topic_id' => $otherTopic->id,
        'description' => 'Unrelated geometry question about circles and tangents.',
    ]);

    $this->actingAs($scenario['teacherUser'])
        ->get(route('teacher.requests.index'))
        ->assertOk()
        ->assertSee('Algebra')
        ->assertDontSee('Unrelated geometry question about circles and tangents.');
});

it('accepts a request by holding the slot and expiring sibling proposals', function () {
    Notification::fake();

    $scenario = teacherInboxScenario();
    $rival = RequestResponse::factory()->create(['tutoring_request_id' => $scenario['request']->id]);

    $startsAt = $scenario['windowStart']->setTime(18, 0);

    $this->actingAs($scenario['teacherUser'])
        ->post(route('teacher.requests.respond', $scenario['request']), [
            'action' => 'accept',
            'starts_at' => $startsAt->toIso8601String(),
            'message' => 'Bring your worksheet.',
        ])
        ->assertRedirect(route('teacher.requests.index'));

    $booking = Booking::query()->firstOrFail();

    expect($booking->status)->toBe(BookingStatus::PendingPayment)
        ->and($booking->teacher_profile_id)->toBe($scenario['teacher']->id)
        ->and($booking->student_id)->toBe($scenario['student']->id)
        ->and($booking->tutoring_request_id)->toBe($scenario['request']->id)
        ->and($booking->price_minor)->toBe(60000)
        ->and($booking->platform_fee_minor)->toBe(9000)
        ->and($booking->teacher_payout_minor)->toBe(51000)
        ->and($booking->starts_at->timestamp)->toBe($startsAt->timestamp)
        ->and($booking->expires_at->lessThanOrEqualTo(now()->addMinutes(31)))->toBeTrue()
        ->and($scenario['request']->fresh()->status)->toBe(RequestStatus::Matched)
        ->and($rival->fresh()->status)->toBe(ResponseStatus::Expired);

    $response = $scenario['request']->responses()
        ->where('teacher_profile_id', $scenario['teacher']->id)
        ->firstOrFail();

    expect($response->status)->toBe(ResponseStatus::Accepted);

    Notification::assertSentTo($scenario['student'], RequestResponseAccepted::class);
});

it('offers the same price it quotes in the response', function () {
    Notification::fake();

    $scenario = teacherInboxScenario();

    $this->actingAs($scenario['teacherUser'])
        ->get(route('teacher.requests.index'))
        ->assertOk()
        ->assertSee('600');

    $this->actingAs($scenario['teacherUser'])
        ->post(route('teacher.requests.respond', $scenario['request']), [
            'action' => 'accept',
            'starts_at' => $scenario['windowStart']->setTime(18, 0)->toIso8601String(),
        ]);

    expect($scenario['request']->responses()->firstOrFail()->price_minor)
        ->toBe($scenario['teacher']->fresh()->effectiveRateFor($scenario['subject']));
});

it('rejects accepting a slot that another booking already blocks', function () {
    $scenario = teacherInboxScenario();

    Booking::factory()->hold()->create([
        'teacher_profile_id' => $scenario['teacher']->id,
        'starts_at' => $scenario['windowStart']->setTime(18, 0),
        'ends_at' => $scenario['windowStart']->setTime(19, 0),
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->actingAs($scenario['teacherUser'])
        ->from(route('teacher.requests.index'))
        ->post(route('teacher.requests.respond', $scenario['request']), [
            'action' => 'accept',
            'starts_at' => $scenario['windowStart']->setTime(18, 0)->toIso8601String(),
        ])
        ->assertSessionHasErrors('starts_at');

    expect($scenario['request']->fresh()->status)->toBe(RequestStatus::Open)
        ->and(Booking::query()->count())->toBe(1);
});

it('only accepts lesson times inside the teacher availability', function () {
    $scenario = teacherInboxScenario();

    $this->actingAs($scenario['teacherUser'])
        ->post(route('teacher.requests.respond', $scenario['request']), [
            'action' => 'accept',
            'starts_at' => $scenario['windowStart']->setTime(9, 0)->toIso8601String(),
        ])
        ->assertSessionHasErrors('starts_at');

    expect(Booking::query()->count())->toBe(0);
});

it('declines a request and keeps it open for other teachers', function () {
    Notification::fake();

    $scenario = teacherInboxScenario();

    $this->actingAs($scenario['teacherUser'])
        ->post(route('teacher.requests.respond', $scenario['request']), [
            'action' => 'decline',
            'message' => 'Sorry, that evening does not work for me.',
        ])
        ->assertRedirect(route('teacher.requests.index'));

    $response = $scenario['request']->responses()->firstOrFail();

    expect($response->status)->toBe(ResponseStatus::Declined)
        ->and($response->message)->toBe('Sorry, that evening does not work for me.')
        ->and($scenario['request']->fresh()->status)->toBe(RequestStatus::Open)
        ->and(Booking::query()->count())->toBe(0);

    Notification::assertSentTo($scenario['student'], RequestResponseDeclined::class);
});

it('stops teachers from responding to topics they do not teach', function () {
    $scenario = teacherInboxScenario();

    $outsider = TeacherProfile::factory()->approved()->create(['timezone' => 'UTC']);

    $this->actingAs($outsider->user)
        ->post(route('teacher.requests.respond', $scenario['request']), [
            'action' => 'accept',
            'starts_at' => $scenario['windowStart']->setTime(18, 0)->toIso8601String(),
        ])
        ->assertSessionHasErrors('status');

    expect(Booking::query()->count())->toBe(0)
        ->and($scenario['request']->fresh()->status)->toBe(RequestStatus::Open);
});

it('marks requests the teacher already answered', function () {
    $scenario = teacherInboxScenario();

    RequestResponse::factory()->create([
        'tutoring_request_id' => $scenario['request']->id,
        'teacher_profile_id' => $scenario['teacher']->id,
    ]);

    $this->actingAs($scenario['teacherUser'])
        ->get(route('teacher.requests.index'))
        ->assertOk()
        ->assertSee('You responded')
        ->assertSee('My proposals');
});
