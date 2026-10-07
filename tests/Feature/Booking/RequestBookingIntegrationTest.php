<?php

use App\Enums\BookingStatus;
use App\Enums\RequestStatus;
use App\Models\Booking;
use App\Models\Lesson;
use App\Models\Subject;
use App\Models\TeacherAvailabilitySlot;
use App\Models\TeacherProfile;
use App\Models\TutoringRequest;
use App\Models\User;
use App\Services\BookingTransitionService;
use App\Services\Payments\PaymentService;
use App\Services\RequestResponseService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

/**
 * A verified teacher, a student question inside their evening window, and the
 * accepted proposal that turns into a payable hold.
 *
 * @return array<string, mixed>
 */
function acceptedRequestScenario(): array
{
    $subject = Subject::factory()->create(['name' => 'Chemistry', 'slug' => 'chemistry']);
    $lesson = Lesson::factory()->create([
        'subject_id' => $subject->id,
        'name' => 'Organic chemistry',
        'slug' => 'organic-chemistry',
    ]);

    $teacherUser = User::factory()->teacher()->create();
    $teacher = TeacherProfile::factory()->approved()->create([
        'user_id' => $teacherUser->id,
        'timezone' => 'UTC',
        'hourly_rate_minor' => 80000,
        'lesson_duration_minutes' => 60,
    ]);
    $teacher->subjects()->attach($subject->id, ['grade_levels' => [(string) gradeId(11)]]);
    $teacher->lessons()->attach($lesson->id);

    $slot = CarbonImmutable::now('UTC')->addDays(2)->setTime(18, 0);
    TeacherAvailabilitySlot::factory()
        ->on($slot->dayOfWeek, '18:00', '20:00')
        ->create(['teacher_profile_id' => $teacher->id]);

    $student = User::factory()->student()->onboarded()->create();

    $request = TutoringRequest::factory()->create([
        'student_id' => $student->id,
        'subject_id' => $subject->id,
        'lesson_id' => $lesson->id,
        'preferred_windows' => [[
            'starts_at' => $slot->toIso8601String(),
            'ends_at' => $slot->addHours(2)->toIso8601String(),
        ]],
    ]);

    $response = app(RequestResponseService::class)->accept($request, $teacher, $slot, $slot->addHour());

    return [
        'subject' => $subject,
        'lesson' => $lesson,
        'teacher' => $teacher,
        'teacherUser' => $teacherUser,
        'student' => $student,
        'request' => $request,
        'response' => $response,
        'slot' => $slot,
    ];
}

it('turns an accepted request into a payable hold the student confirms', function () {
    Notification::fake();

    $scenario = acceptedRequestScenario();
    $booking = Booking::query()->firstOrFail();

    expect($booking->status)->toBe(BookingStatus::PendingPayment)
        ->and($booking->tutoring_request_id)->toBe($scenario['request']->id)
        ->and($booking->price_minor)->toBe(80000)
        ->and($booking->learner_grade_id)->toBe($scenario['student']->studentProfile->grade_id);

    $this->actingAs($scenario['student'])
        ->get(route('student.requests.show', $scenario['request']))
        ->assertOk()
        ->assertSee('Your teacher is holding this slot')
        ->assertSee('Complete payment');

    $payment = app(PaymentService::class)->startCheckout($booking);
    app(PaymentService::class)->capture($payment, 'pay_fake_test', 'upi');

    expect($booking->refresh()->status)->toBe(BookingStatus::Confirmed);

    $this->actingAs($scenario['student'])
        ->get(route('student.requests.show', $scenario['request']))
        ->assertOk()
        ->assertSee('View lesson');
});

it('closes the student question once the lesson is delivered', function () {
    Notification::fake();

    $scenario = acceptedRequestScenario();
    $booking = Booking::query()->firstOrFail();

    app(BookingTransitionService::class)->confirm($booking);

    $booking->forceFill([
        'starts_at' => now()->subHour(),
        'ends_at' => now()->subMinutes(10),
    ])->save();

    app(BookingTransitionService::class)->complete($booking);

    expect($booking->refresh()->status)->toBe(BookingStatus::Completed)
        ->and($scenario['request']->refresh()->status)->toBe(RequestStatus::Closed);
});

it('shows the lesson in the student lesson list after the hold is paid', function () {
    Notification::fake();

    $scenario = acceptedRequestScenario();
    $booking = Booking::query()->firstOrFail();

    app(BookingTransitionService::class)->confirm($booking);

    $this->actingAs($scenario['student'])
        ->get(route('student.bookings.index'))
        ->assertOk()
        ->assertSee($scenario['teacherUser']->name)
        ->assertSee('Confirmed')
        ->assertSee('800');
});
