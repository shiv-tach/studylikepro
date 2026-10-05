<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Notifications\BookingConfirmed;
use App\Notifications\LessonReminder;
use App\Notifications\PaymentFailed;
use App\Notifications\PayoutPaid;
use App\Services\BookingTransitionService;
use App\Services\Payments\FakePaymentGateway;
use App\Services\Payments\PayoutService;
use Illuminate\Support\Facades\Notification;

it('sends a day-ahead reminder and then the final one', function () {
    Notification::fake();

    $scenario = classroomScenario(minutesFromNow: 60 * 20);
    $booking = $scenario['booking'];

    $this->artisan('studylikepro:send-lesson-reminders')->assertSuccessful();

    Notification::assertSentTo($scenario['student'], LessonReminder::class);
    Notification::assertSentTo($scenario['teacherUser'], LessonReminder::class);

    $booking->refresh();

    expect($booking->reminder_day_sent_at)->not->toBeNull()
        ->and($booking->reminder_sent_at)->toBeNull();

    // The day-ahead reminder is not repeated.
    $this->artisan('studylikepro:send-lesson-reminders')->assertSuccessful();
    Notification::assertSentToTimes($scenario['student'], LessonReminder::class, 1);

    // Closer to the lesson the final reminder goes out on its own.
    $this->travelTo($booking->starts_at->subMinutes(30));
    $this->artisan('studylikepro:send-lesson-reminders')->assertSuccessful();

    Notification::assertSentToTimes($scenario['student'], LessonReminder::class, 2);
    Notification::assertSentToTimes($scenario['teacherUser'], LessonReminder::class, 2);

    expect($booking->refresh()->reminder_sent_at)->not->toBeNull();
});

it('sends only the final reminder for a lesson booked shortly before it starts', function () {
    Notification::fake();

    $scenario = classroomScenario(minutesFromNow: 45);

    $this->artisan('studylikepro:send-lesson-reminders')->assertSuccessful();

    Notification::assertSentToTimes($scenario['student'], LessonReminder::class, 1);
});

it('tells the student when a payment fails', function () {
    Notification::fake();

    $booking = ownedBooking();
    $student = $booking->student;

    $this->actingAs($student)->post(route('student.bookings.checkout', $booking));
    $payment = $booking->payments()->firstOrFail();

    [$body, $signature] = app(FakePaymentGateway::class)->localFailurePayload($payment);

    $this->post(route('payments.webhook', 'fake'), [
        'payload' => (string) json_encode($body),
        'signature' => $signature,
    ])->assertOk();

    Notification::assertSentTo($student, PaymentFailed::class);
});

it('tells the teacher when a payout leaves the bank', function () {
    Notification::fake();

    $scenario = paidBookingScenario();

    // Delivering the lesson makes the earning available to batch.
    $this->travelTo($scenario['booking']->starts_at->addMinutes(5));
    app(BookingTransitionService::class)->complete($scenario['booking']);

    $payout = app(PayoutService::class)->createFor($scenario['teacher']);
    app(PayoutService::class)->markPaid($payout, 'UTR-99881');

    Notification::assertSentTo($scenario['teacherUser'], PayoutPaid::class);
});

it('leaves a booking on hold when the gateway refuses the payment', function () {
    $booking = ownedBooking();

    $this->actingAs($booking->student)->post(route('student.bookings.checkout', $booking));
    $payment = $booking->payments()->firstOrFail();

    [$body, $signature] = app(FakePaymentGateway::class)->localFailurePayload($payment);

    $this->post(route('payments.webhook', 'fake'), [
        'payload' => (string) json_encode($body),
        'signature' => $signature,
    ])->assertOk();

    expect($payment->refresh()->status->value)->toBe('failed')
        ->and($booking->refresh()->status)->toBe(BookingStatus::PendingPayment);
});

it('never emails a receipt-less booking twice for the same capture', function () {
    Notification::fake();

    $booking = ownedBooking();

    $this->actingAs($booking->student)->post(route('student.bookings.checkout', $booking));
    $payment = $booking->payments()->firstOrFail();

    [$body, $signature] = app(FakePaymentGateway::class)->localCapturePayload($payment);
    $payload = (string) json_encode($body);

    $this->post(route('payments.webhook', 'fake'), ['payload' => $payload, 'signature' => $signature])->assertOk();
    $this->post(route('payments.webhook', 'fake'), ['payload' => $payload, 'signature' => $signature])->assertOk();

    Notification::assertSentToTimes($booking->student, BookingConfirmed::class, 1);
});

it('marks an earning paid exactly once', function () {
    $scenario = paidBookingScenario();

    $this->travelTo($scenario['booking']->starts_at->addMinutes(5));
    app(BookingTransitionService::class)->complete($scenario['booking']);

    $payout = app(PayoutService::class)->createFor($scenario['teacher']);

    expect($payout)->not->toBeNull()
        ->and($payout->amount_minor)->toBe($scenario['earning']->amount_minor)
        ->and(Payment::query()->count())->toBe(1)
        ->and(Booking::query()->count())->toBe(1);
});
