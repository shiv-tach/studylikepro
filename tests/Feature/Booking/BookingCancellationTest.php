<?php

use App\Enums\BookingStatus;
use App\Enums\RequestStatus;
use App\Models\Booking;
use App\Models\TeacherProfile;
use App\Models\TutoringRequest;
use App\Models\User;
use App\Notifications\BookingCancelled;
use App\Services\BookingTransitionService;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

/**
 * A confirmed lesson with an onboarded student and an approved teacher,
 * `hours` from now.
 *
 * @return array{booking: Booking, student: User, teacherUser: User}
 */
function cancellableBooking(int $hours = 72, BookingStatus $status = BookingStatus::Confirmed): array
{
    $student = User::factory()->student()->onboarded()->create();
    $teacherUser = User::factory()->teacher()->create();
    $teacher = TeacherProfile::factory()->approved()->create(['user_id' => $teacherUser->id]);

    $booking = Booking::factory()->create([
        'student_id' => $student->id,
        'teacher_profile_id' => $teacher->id,
        'status' => $status,
        'starts_at' => now()->addHours($hours),
        'ends_at' => now()->addHours($hours)->addHour(),
    ]);

    return [
        'booking' => $booking,
        'student' => $student,
        'teacherUser' => $teacherUser,
    ];
}

it('lets a student release a hold at any time', function () {
    Notification::fake();

    $scenario = cancellableBooking(hours: 1, status: BookingStatus::PendingPayment);
    $scenario['booking']->forceFill(['expires_at' => now()->addMinutes(20)])->save();

    $this->actingAs($scenario['student'])
        ->post(route('student.bookings.cancel', $scenario['booking']), ['reason' => 'Change of plans'])
        ->assertRedirect(route('student.bookings.show', $scenario['booking']))
        ->assertSessionHasNoErrors();

    $booking = $scenario['booking']->refresh();

    expect($booking->status)->toBe(BookingStatus::Cancelled)
        ->and($booking->cancelled_by)->toBe(Booking::CANCELLED_BY_STUDENT)
        ->and($booking->cancellation_reason)->toBe('Change of plans')
        ->and($booking->cancelled_at)->not->toBeNull();

    Notification::assertSentTo($scenario['teacherUser'], BookingCancelled::class);
});

it('lets a student cancel a confirmed lesson outside the cancellation window', function () {
    Notification::fake();

    $scenario = cancellableBooking(hours: 72);

    $this->actingAs($scenario['student'])
        ->post(route('student.bookings.cancel', $scenario['booking']))
        ->assertSessionHasNoErrors();

    expect($scenario['booking']->refresh()->status)->toBe(BookingStatus::Cancelled);

    Notification::assertSentTo($scenario['teacherUser'], BookingCancelled::class);
});

it('blocks student self-service cancellation inside the 24 hour window', function () {
    Notification::fake();

    $scenario = cancellableBooking(hours: 2);

    $this->actingAs($scenario['student'])
        ->post(route('student.bookings.cancel', $scenario['booking']))
        ->assertStatus(403);

    expect($scenario['booking']->refresh()->status)->toBe(BookingStatus::Confirmed);

    $this->actingAs($scenario['student'])
        ->get(route('student.bookings.show', $scenario['booking']))
        ->assertOk()
        ->assertSee('self-service cancellation is closed')
        ->assertDontSee('Cancel lesson');

    Notification::assertNothingSent();
});

it('surfaces the cancellation window error when the booking state allows it but the window has closed', function () {
    Notification::fake();

    $scenario = cancellableBooking(hours: 2);

    expect(fn () => app(BookingTransitionService::class)
        ->cancel($scenario['booking'], Booking::CANCELLED_BY_STUDENT))
        ->toThrow(ValidationException::class);

    expect($scenario['booking']->refresh()->status)->toBe(BookingStatus::Confirmed);
});

it('lets a teacher cancel a lesson even inside the window', function () {
    Notification::fake();

    $scenario = cancellableBooking(hours: 2);

    $this->actingAs($scenario['teacherUser'])
        ->post(route('teacher.bookings.cancel', $scenario['booking']), ['reason' => 'Family emergency'])
        ->assertSessionHasNoErrors();

    $booking = $scenario['booking']->refresh();

    expect($booking->status)->toBe(BookingStatus::Cancelled)
        ->and($booking->cancelled_by)->toBe(Booking::CANCELLED_BY_TEACHER)
        ->and($booking->cancellation_reason)->toBe('Family emergency');

    Notification::assertSentTo($scenario['student'], BookingCancelled::class);
    Notification::assertNotSentTo($scenario['teacherUser'], BookingCancelled::class);
});

it('lets an admin cancel any live booking', function () {
    Notification::fake();

    $scenario = cancellableBooking(hours: 4);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.bookings.cancel', $scenario['booking']), ['reason' => 'Duplicate booking'])
        ->assertRedirect(route('admin.bookings.show', $scenario['booking']))
        ->assertSessionHasNoErrors();

    expect($scenario['booking']->refresh()->cancelled_by)->toBe(Booking::CANCELLED_BY_ADMIN);

    Notification::assertSentTo($scenario['student'], BookingCancelled::class);
});

it('refuses to cancel a lesson that is already finished', function () {
    $scenario = cancellableBooking(hours: -3, status: BookingStatus::Completed);

    $this->actingAs($scenario['student'])
        ->post(route('student.bookings.cancel', $scenario['booking']))
        ->assertStatus(403);

    $this->actingAs($scenario['teacherUser'])
        ->post(route('teacher.bookings.cancel', $scenario['booking']))
        ->assertStatus(403);

    expect($scenario['booking']->refresh()->status)->toBe(BookingStatus::Completed);
});

it('puts the student question back in front of teachers when the lesson is cancelled', function () {
    Notification::fake();

    $scenario = cancellableBooking(hours: 48, status: BookingStatus::PendingPayment);

    $request = TutoringRequest::factory()->create([
        'student_id' => $scenario['student']->id,
        'status' => RequestStatus::Matched,
        'expires_at' => now()->addDays(3),
    ]);

    $booking = $scenario['booking'];
    $booking->forceFill([
        'tutoring_request_id' => $request->id,
        'expires_at' => now()->addMinutes(20),
    ])->save();

    $this->actingAs($scenario['student'])
        ->post(route('student.bookings.cancel', $booking))
        ->assertSessionHasNoErrors();

    expect($request->refresh()->status)->toBe(RequestStatus::Open);
});

it('leaves an expired question closed when its lesson is cancelled', function () {
    Notification::fake();

    $scenario = cancellableBooking(hours: 48, status: BookingStatus::PendingPayment);

    $request = TutoringRequest::factory()->create([
        'student_id' => $scenario['student']->id,
        'status' => RequestStatus::Matched,
        'expires_at' => now()->subDay(),
    ]);

    $booking = $scenario['booking'];
    $booking->forceFill([
        'tutoring_request_id' => $request->id,
        'expires_at' => now()->addMinutes(20),
    ])->save();

    $this->actingAs($scenario['student'])
        ->post(route('student.bookings.cancel', $booking))
        ->assertSessionHasNoErrors();

    expect($request->refresh()->status)->toBe(RequestStatus::Matched);
});
