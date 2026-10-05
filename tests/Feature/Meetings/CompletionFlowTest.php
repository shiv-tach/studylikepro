<?php

use App\Enums\BookingStatus;
use App\Enums\EarningStatus;
use App\Jobs\CreateBookingMeeting;
use App\Models\Payment;
use App\Notifications\LessonCompleted;
use App\Services\BookingTransitionService;
use App\Services\Meetings\FakeMeetingProvider;
use App\Services\Payments\EarningsService;
use Illuminate\Support\Facades\Notification;

it('releases the payout and prompts both parties when a lesson is delivered', function () {
    Notification::fake();

    $scenario = classroomScenario();
    $booking = $scenario['booking'];

    $payment = Payment::factory()->create([
        'booking_id' => $booking->id,
        'student_id' => $booking->student_id,
        'amount_minor' => $booking->price_minor,
    ]);

    $earning = app(EarningsService::class)->recordForBooking($booking, $payment);

    expect($earning->status)->toBe(EarningStatus::Pending);

    $this->travelTo($booking->starts_at->addMinutes(10));
    app(BookingTransitionService::class)->complete($booking);

    expect($earning->fresh()->status)->toBe(EarningStatus::Eligible);

    Notification::assertSentTo($booking->student, LessonCompleted::class);
    Notification::assertSentTo($booking->teacherProfile->user, LessonCompleted::class);

    $booking->refresh();

    expect($booking->meeting_ended_at)->not->toBeNull()
        ->and(FakeMeetingProvider::endedRooms())->toContain($booking->meeting_external_id);
});

it('closes the room and stays quiet when the teacher reports a no-show', function () {
    Notification::fake();

    $scenario = classroomScenario();
    $booking = $scenario['booking'];

    $this->travelTo($booking->starts_at->addMinutes(2));

    $this->actingAs($scenario['teacherUser'])
        ->post(route('teacher.bookings.no-show', $booking))
        ->assertRedirect();

    Notification::assertNotSentTo($booking->student, LessonCompleted::class);
    Notification::assertNotSentTo($scenario['teacherUser'], LessonCompleted::class);

    expect($booking->fresh()->meeting_ended_at)->not->toBeNull()
        ->and(FakeMeetingProvider::endedRooms())->toHaveCount(1);
});

it('shows the join call to action on both booking pages while the room is live', function () {
    $scenario = classroomScenario();
    $booking = $scenario['booking'];

    $this->actingAs($scenario['student'])
        ->get(route('student.bookings.show', $booking))
        ->assertOk()
        ->assertSee('Your classroom is open')
        ->assertSee('Join lesson');

    $this->actingAs($scenario['teacherUser'])
        ->get(route('teacher.bookings.show', $booking))
        ->assertOk()
        ->assertSee('Open now — your student can join.')
        ->assertSee('Join lesson');
});

it('adds the classroom link to the student lesson list', function () {
    $scenario = classroomScenario();
    $booking = $scenario['booking'];

    $this->actingAs($scenario['student'])
        ->get(route('student.bookings.index'))
        ->assertOk()
        ->assertSee('Join now');
});

it('does not open a classroom for a lesson that never got confirmed', function () {
    $scenario = classroomScenario(withRoom: false, overrides: [
        'status' => BookingStatus::Cancelled,
        'cancelled_at' => now(),
        'cancelled_by' => 'teacher',
    ]);

    CreateBookingMeeting::dispatch($scenario['booking']->id);

    expect($scenario['booking']->fresh()->meeting_status)->toBeNull()
        ->and(FakeMeetingProvider::rooms())->toHaveCount(0);
});
