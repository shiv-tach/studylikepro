<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Lesson;
use App\Models\Subject;
use App\Models\TeacherAvailabilitySlot;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Notifications\BookingExpired;
use App\Services\RequestMatcher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

/**
 * Two students, one teacher, one evening window two days out.
 *
 * @return array<string, mixed>
 */
function doubleBookingScenario(): array
{
    $subject = Subject::factory()->create(['name' => 'Mathematics', 'slug' => 'mathematics']);
    $lesson = Lesson::factory()->create([
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
    $teacher->subjects()->attach($subject->id, ['grade_levels' => [(string) gradeId(11)]]);
    $teacher->lessons()->attach($lesson->id);

    $day = CarbonImmutable::now('UTC')->addDays(2)->startOfDay();
    TeacherAvailabilitySlot::factory()
        ->on($day->dayOfWeek, '18:00', '21:00')
        ->create(['teacher_profile_id' => $teacher->id]);

    return [
        'subject' => $subject,
        'lesson' => $lesson,
        'teacher' => $teacher,
        'teacherUser' => $teacherUser,
        'first' => User::factory()->student()->onboarded()->create(),
        'second' => User::factory()->student()->onboarded()->create(),
        'slot' => $day->setTime(18, 0),
    ];
}

it('refuses a second booking for a slot a live hold already blocks', function () {
    Notification::fake();

    $scenario = doubleBookingScenario();

    $this->actingAs($scenario['first'])
        ->post(route('student.bookings.store', $scenario['teacher']), [
            'subject_id' => $scenario['subject']->id,
            'duration' => 60,
            'starts_at' => $scenario['slot']->toIso8601String(),
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($scenario['second'])
        ->post(route('student.bookings.store', $scenario['teacher']), [
            'subject_id' => $scenario['subject']->id,
            'duration' => 60,
            'starts_at' => $scenario['slot']->toIso8601String(),
        ])
        ->assertSessionHasErrors('starts_at');

    expect(Booking::query()->count())->toBe(1)
        ->and(Booking::query()->firstOrFail()->student_id)->toBe($scenario['first']->id);
});

it('re-checks the slot inside the booking transaction so a late rival cannot win', function () {
    Notification::fake();

    $scenario = doubleBookingScenario();

    // Both students see the slot on the booking page...
    $this->actingAs($scenario['second'])
        ->get(route('student.bookings.create', $scenario['teacher']))
        ->assertOk()
        ->assertSee('18:00');

    // ...then the first one books it before the second submits.
    $this->actingAs($scenario['first'])
        ->post(route('student.bookings.store', $scenario['teacher']), [
            'subject_id' => $scenario['subject']->id,
            'duration' => 60,
            'starts_at' => $scenario['slot']->toIso8601String(),
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($scenario['second'])
        ->post(route('student.bookings.store', $scenario['teacher']), [
            'subject_id' => $scenario['subject']->id,
            'duration' => 60,
            'starts_at' => $scenario['slot']->toIso8601String(),
        ])
        ->assertSessionHasErrors('starts_at');

    expect(Booking::query()->count())->toBe(1);
});

it('hides a held slot from other students on the booking page', function () {
    Notification::fake();

    $scenario = doubleBookingScenario();

    Booking::factory()->hold()->create([
        'student_id' => $scenario['first']->id,
        'teacher_profile_id' => $scenario['teacher']->id,
        'starts_at' => $scenario['slot'],
        'ends_at' => $scenario['slot']->addHour(),
    ]);

    $this->actingAs($scenario['second'])
        ->get(route('student.bookings.create', $scenario['teacher']))
        ->assertOk()
        ->assertDontSee($scenario['slot']->toIso8601String())
        ->assertSee($scenario['slot']->addHour()->toIso8601String());
});

it('drops booked slots from the public teacher profile preview', function () {
    $scenario = doubleBookingScenario();

    $before = preg_match_all(
        '/>\d{2}:\d{2}<\/span>/',
        $this->get(route('teachers.show', $scenario['teacher']))->assertOk()->getContent(),
    );

    Booking::factory()->create([
        'student_id' => $scenario['first']->id,
        'teacher_profile_id' => $scenario['teacher']->id,
        'starts_at' => $scenario['slot'],
        'ends_at' => $scenario['slot']->addHour(),
    ]);

    $after = preg_match_all(
        '/>\d{2}:\d{2}<\/span>/',
        $this->get(route('teachers.show', $scenario['teacher']))->assertOk()->getContent(),
    );

    expect($before)->toBeGreaterThan(0)
        ->and($after)->toBe($before - 1);
});

it('keeps a live hold blocking the slot until it is paid or expires', function () {
    Notification::fake();

    $scenario = doubleBookingScenario();

    $hold = Booking::factory()->hold()->create([
        'student_id' => $scenario['first']->id,
        'teacher_profile_id' => $scenario['teacher']->id,
        'starts_at' => $scenario['slot'],
        'ends_at' => $scenario['slot']->addHour(),
        'expires_at' => now()->addMinutes(5),
    ]);

    $this->artisan('studylikepro:expire-holds')->assertSuccessful();

    expect($hold->refresh()->status)->toBe(BookingStatus::PendingPayment)
        ->and(app(RequestMatcher::class)->isSlotOpen($scenario['teacher'], $scenario['slot'], $scenario['slot']->addHour()))->toBeFalse();
});

it('releases the slot and warns the student when a hold expires', function () {
    Notification::fake();

    $scenario = doubleBookingScenario();

    $hold = Booking::factory()->hold()->create([
        'student_id' => $scenario['first']->id,
        'teacher_profile_id' => $scenario['teacher']->id,
        'starts_at' => $scenario['slot'],
        'ends_at' => $scenario['slot']->addHour(),
        'expires_at' => now()->subMinute(),
    ]);

    $this->artisan('studylikepro:expire-holds')->assertSuccessful();

    expect($hold->refresh()->status)->toBe(BookingStatus::Expired);

    Notification::assertSentTo($scenario['first'], BookingExpired::class);

    $this->actingAs($scenario['second'])
        ->post(route('student.bookings.store', $scenario['teacher']), [
            'subject_id' => $scenario['subject']->id,
            'duration' => 60,
            'starts_at' => $scenario['slot']->toIso8601String(),
        ])
        ->assertSessionHasNoErrors();

    expect(Booking::query()->where('status', BookingStatus::PendingPayment->value)->count())->toBe(1);
});
