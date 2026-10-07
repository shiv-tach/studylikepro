<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Lesson;
use App\Models\Subject;
use App\Models\TeacherAvailabilitySlot;
use App\Models\TeacherProfile;
use App\Models\TutoringRequest;
use App\Models\User;
use App\Notifications\BookingConfirmed;
use App\Notifications\BookingRequestReceived;
use App\Services\BookingTransitionService;
use App\Services\Payments\PaymentService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Notification;

it('tells both parties the moment a hold is paid for', function () {
    Notification::fake();

    $booking = ownedBooking();
    $payments = app(PaymentService::class);

    $payment = $payments->startCheckout($booking);
    $payments->capture($payment, 'pay_fake_test', 'upi');

    expect($booking->refresh()->status)->toBe(BookingStatus::Confirmed);

    Notification::assertSentTo($booking->student, BookingConfirmed::class);
    Notification::assertSentTo($booking->teacherProfile->user, BookingConfirmed::class);
});

it('does not tell a teacher about the hold their own accepted request created', function () {
    Notification::fake();

    $request = TutoringRequest::factory()->create();

    $booking = Booking::factory()->hold()->create([
        'tutoring_request_id' => $request->id,
        'student_id' => $request->student_id,
    ]);

    app(BookingTransitionService::class)->confirm($booking);

    Notification::assertNotSentTo($booking->teacherProfile->user, BookingRequestReceived::class);
    Notification::assertSentTo($booking->teacherProfile->user, BookingConfirmed::class);
});

it('warns the teacher about a student-initiated hold with the payout they will earn', function () {
    Notification::fake();

    $scenario = prepareBookingScenario();

    $this->actingAs($scenario['student'])
        ->post(route('student.bookings.store', $scenario['teacher']), [
            'subject_id' => $scenario['subject']->id,
            'duration' => 60,
            'starts_at' => $scenario['slot']->toIso8601String(),
        ])
        ->assertSessionHasNoErrors();

    Notification::assertSentTo(
        $scenario['teacherUser'],
        BookingRequestReceived::class,
        fn (BookingRequestReceived $notification) => $notification->booking->teacher_payout_minor === 51000
            && str_contains($notification->toDatabase($scenario['teacherUser'])['title'], 'booked a lesson'),
    );
});

it('stores the confirmation in the database channel for both sides', function () {
    $booking = Booking::factory()->hold()->create();

    app(BookingTransitionService::class)->confirm($booking);

    expect($booking->student->notifications()->count())->toBe(1)
        ->and($booking->teacherProfile->user->notifications()->count())->toBe(1)
        ->and($booking->student->notifications()->first()->data['title'])
        ->toContain('Lesson confirmed');
});

/**
 * An approved teacher with an evening window two days out and a student ready to book.
 *
 * @return array<string, mixed>
 */
function prepareBookingScenario(): array
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
        'student' => User::factory()->student()->onboarded()->create(),
        'slot' => $day->setTime(18, 0),
    ];
}
