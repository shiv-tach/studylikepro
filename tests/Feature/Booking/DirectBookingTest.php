<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Subject;
use App\Models\TeacherAvailabilitySlot;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Notifications\BookingRequestReceived;
use App\Services\RequestMatcher;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

/**
 * An approved maths teacher with a two-hour evening window two days out,
 * a Grade 11 lesson, and a Grade 11 student ready to book directly into it.
 *
 * @return array<string, mixed>
 */
function directBookingScenario(int $durationMinutes = 60): array
{
    $gradeId = gradeId(11);

    $subject = Subject::factory()->create([
        'name' => 'Mathematics',
        'slug' => 'mathematics',
        'education_level_id' => Grade::query()->whereKey($gradeId)->value('education_level_id'),
    ]);
    $lesson = Lesson::factory()->create([
        'subject_id' => $subject->id,
        'grade_id' => $gradeId,
        'name' => 'Algebra',
        'slug' => 'algebra',
    ]);

    $teacherUser = User::factory()->teacher()->create();
    $teacher = TeacherProfile::factory()->approved()->create([
        'user_id' => $teacherUser->id,
        'timezone' => 'UTC',
        'hourly_rate_minor' => 60000,
        'lesson_duration_minutes' => $durationMinutes,
    ]);
    $teacher->subjects()->attach($subject->id, ['grade_levels' => [(string) $gradeId]]);
    $teacher->lessons()->attach($lesson->id);

    $day = CarbonImmutable::now('UTC')->addDays(2)->startOfDay();
    TeacherAvailabilitySlot::factory()
        ->on($day->dayOfWeek, '18:00', '21:00')
        ->create(['teacher_profile_id' => $teacher->id]);

    $student = User::factory()->student()->onboarded()->create();
    $student->studentProfile->update(['grade_id' => $gradeId]);

    return [
        'subject' => $subject,
        'lesson' => $lesson,
        'teacher' => $teacher,
        'teacherUser' => $teacherUser,
        'student' => $student,
        'day' => $day,
        'slot' => $day->setTime(18, 0),
    ];
}

it('shows the direct booking page with open slots and the lesson price', function () {
    $scenario = directBookingScenario();

    $this->actingAs($scenario['student'])
        ->get(route('student.bookings.create', $scenario['teacher']))
        ->assertOk()
        ->assertSee($scenario['teacherUser']->name)
        ->assertSee('600')
        ->assertSee('18:00')
        ->assertSee('Reserve this slot');
});

it('reserves the chosen slot as a pending payment hold with the commission snapshot', function () {
    Notification::fake();

    $scenario = directBookingScenario();

    $this->actingAs($scenario['student'])
        ->post(route('student.bookings.store', $scenario['teacher']), [
            'subject_id' => $scenario['subject']->id,
            'lesson_id' => $scenario['lesson']->id,
            'duration' => 60,
            'starts_at' => $scenario['slot']->toIso8601String(),
            'learner_name' => 'Riya Sharma',
            'learner_grade_id' => gradeId(11),
        ])
        ->assertSessionHasNoErrors();

    $booking = Booking::query()->firstOrFail();

    expect($booking->status)->toBe(BookingStatus::PendingPayment)
        ->and($booking->student_id)->toBe($scenario['student']->id)
        ->and($booking->teacher_profile_id)->toBe($scenario['teacher']->id)
        ->and($booking->subject_id)->toBe($scenario['subject']->id)
        ->and($booking->lesson_id)->toBe($scenario['lesson']->id)
        ->and($booking->price_minor)->toBe(60000)
        ->and($booking->commission_percent)->toBe(15)
        ->and($booking->platform_fee_minor)->toBe(9000)
        ->and($booking->teacher_payout_minor)->toBe(51000)
        ->and($booking->currency)->toBe('LKR')
        ->and($booking->learner_name)->toBe('Riya Sharma')
        ->and($booking->learner_grade_id)->toBe(gradeId(11))
        ->and($booking->starts_at->timestamp)->toBe($scenario['slot']->timestamp)
        ->and($booking->ends_at->timestamp)->toBe($scenario['slot']->addHour()->timestamp)
        ->and($booking->expires_at->lessThanOrEqualTo(now()->addMinutes(31)))->toBeTrue();

    expect(app(RequestMatcher::class)->isSlotOpen($scenario['teacher'], $booking->starts_at, $booking->ends_at))
        ->toBeFalse();

    Notification::assertSentTo($scenario['teacherUser'], BookingRequestReceived::class);
});

it('prices shorter lessons pro rata', function () {
    $scenario = directBookingScenario(durationMinutes: 30);

    $this->actingAs($scenario['student'])
        ->post(route('student.bookings.store', $scenario['teacher']), [
            'subject_id' => $scenario['subject']->id,
            'duration' => 30,
            'starts_at' => $scenario['slot']->toIso8601String(),
        ])
        ->assertSessionHasNoErrors();

    $booking = Booking::query()->firstOrFail();

    expect($booking->price_minor)->toBe(30000)
        ->and($booking->platform_fee_minor)->toBe(4500)
        ->and($booking->teacher_payout_minor)->toBe(25500)
        ->and($booking->durationMinutes())->toBe(30)
        ->and($booking->learner_name)->toBe($scenario['student']->name)
        ->and($booking->learner_grade_id)->toBe($scenario['student']->studentProfile->grade_id);
});

it('uses the per subject rate override when the teacher has one', function () {
    $scenario = directBookingScenario();

    $scenario['teacher']->subjects()->updateExistingPivot($scenario['subject']->id, [
        'rate_per_hour_minor' => 90000,
    ]);

    $this->actingAs($scenario['student'])
        ->post(route('student.bookings.store', $scenario['teacher']), [
            'subject_id' => $scenario['subject']->id,
            'duration' => 60,
            'starts_at' => $scenario['slot']->toIso8601String(),
        ])
        ->assertSessionHasNoErrors();

    expect(Booking::query()->firstOrFail()->price_minor)->toBe(90000);
});

it('rejects a subject the teacher does not offer', function () {
    $scenario = directBookingScenario();
    $other = Subject::factory()->create(['name' => 'Chemistry', 'slug' => 'chemistry']);

    $this->actingAs($scenario['student'])
        ->post(route('student.bookings.store', $scenario['teacher']), [
            'subject_id' => $other->id,
            'duration' => 60,
            'starts_at' => $scenario['slot']->toIso8601String(),
        ])
        ->assertSessionHasErrors('subject_id');

    expect(Booking::query()->count())->toBe(0);
});

it('rejects a lesson that belongs to another subject', function () {
    $scenario = directBookingScenario();
    $foreignLesson = Lesson::factory()->create([
        'subject_id' => Subject::factory()->create(['name' => 'Physics', 'slug' => 'physics'])->id,
        'name' => 'Optics',
        'slug' => 'optics',
    ]);

    $this->actingAs($scenario['student'])
        ->post(route('student.bookings.store', $scenario['teacher']), [
            'subject_id' => $scenario['subject']->id,
            'lesson_id' => $foreignLesson->id,
            'duration' => 60,
            'starts_at' => $scenario['slot']->toIso8601String(),
        ])
        ->assertSessionHasErrors('lesson_id');

    expect(Booking::query()->count())->toBe(0);
});

it('offers only the learner grade lessons on the booking page', function () {
    $scenario = directBookingScenario();

    $otherGrade = Grade::factory()->create(['education_level_id' => $scenario['subject']->education_level_id]);
    $otherLesson = Lesson::factory()->create([
        'subject_id' => $scenario['subject']->id,
        'grade_id' => $otherGrade->id,
        'name' => 'Trigonometry',
        'slug' => 'trigonometry',
    ]);
    $scenario['teacher']->lessons()->attach($otherLesson->id);

    $this->actingAs($scenario['student'])
        ->get(route('student.bookings.create', $scenario['teacher']))
        ->assertOk()
        ->assertSee('Algebra')
        ->assertDontSee('Trigonometry');

    $this->actingAs($scenario['student'])
        ->get(route('student.bookings.create', [
            'teacherProfile' => $scenario['teacher'],
            'learner_grade_id' => $otherGrade->id,
        ]))
        ->assertOk()
        ->assertSee('Trigonometry')
        ->assertDontSee('Algebra');
});

it('rejects a lesson that is not for the learner grade', function () {
    $scenario = directBookingScenario();

    $otherGrade = Grade::factory()->create(['education_level_id' => $scenario['subject']->education_level_id]);
    $otherLesson = Lesson::factory()->create([
        'subject_id' => $scenario['subject']->id,
        'grade_id' => $otherGrade->id,
        'name' => 'Trigonometry',
        'slug' => 'trigonometry',
    ]);
    $scenario['teacher']->lessons()->attach($otherLesson->id);

    $this->actingAs($scenario['student'])
        ->post(route('student.bookings.store', $scenario['teacher']), [
            'subject_id' => $scenario['subject']->id,
            'lesson_id' => $otherLesson->id,
            'duration' => 60,
            'starts_at' => $scenario['slot']->toIso8601String(),
            'learner_grade_id' => gradeId(11),
        ])
        ->assertSessionHasErrors('lesson_id');

    expect(Booking::query()->count())->toBe(0);
});

it('rejects a time that is not one of the open slots', function () {
    $scenario = directBookingScenario();

    $this->actingAs($scenario['student'])
        ->post(route('student.bookings.store', $scenario['teacher']), [
            'subject_id' => $scenario['subject']->id,
            'duration' => 60,
            'starts_at' => $scenario['day']->setTime(23, 0)->toIso8601String(),
        ])
        ->assertSessionHasErrors('starts_at');

    expect(Booking::query()->count())->toBe(0);
});

it('rejects a slot that has already started', function () {
    $scenario = directBookingScenario();

    $this->actingAs($scenario['student'])
        ->post(route('student.bookings.store', $scenario['teacher']), [
            'subject_id' => $scenario['subject']->id,
            'duration' => 60,
            'starts_at' => CarbonImmutable::now('UTC')->subHour()->toIso8601String(),
        ])
        ->assertSessionHasErrors('starts_at');

    expect(Booking::query()->count())->toBe(0);
});

it('rejects a lesson length the teacher does not offer', function () {
    $scenario = directBookingScenario();

    $this->actingAs($scenario['student'])
        ->post(route('student.bookings.store', $scenario['teacher']), [
            'subject_id' => $scenario['subject']->id,
            'duration' => 75,
            'starts_at' => $scenario['slot']->toIso8601String(),
        ])
        ->assertSessionHasErrors('duration');

    expect(Booking::query()->count())->toBe(0);
});

it('sends a student to the payment page after reserving', function () {
    $scenario = directBookingScenario();

    $response = $this->actingAs($scenario['student'])
        ->post(route('student.bookings.store', $scenario['teacher']), [
            'subject_id' => $scenario['subject']->id,
            'duration' => 60,
            'starts_at' => $scenario['slot']->toIso8601String(),
        ]);

    $booking = Booking::query()->firstOrFail();

    $response->assertRedirect(route('student.bookings.show', $booking));

    $this->actingAs($scenario['student'])
        ->get(route('student.bookings.show', $booking))
        ->assertOk()
        ->assertSee('Your slot is held');

    // …and the pay button opens a provider order on the checkout page.
    $this->actingAs($scenario['student'])
        ->post(route('student.bookings.checkout', $booking))
        ->assertRedirect(route('student.payments.checkout', $booking->payments()->firstOrFail()));

    $this->actingAs($scenario['student'])
        ->get(route('student.payments.checkout', $booking->payments()->firstOrFail()))
        ->assertOk()
        ->assertSee('Checkout')
        ->assertSee('Demo gateway');
});
