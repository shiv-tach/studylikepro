<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Services\Payments\FakePaymentGateway;
use App\Services\Payments\PaymentService;

it('confirms a payment the gateway already captured when the browser returns', function () {
    $booking = ownedBooking();
    $payment = app(PaymentService::class)->startCheckout($booking);

    $payment->forceFill(['gateway_payment_id' => 'pay_return_1'])->save();

    FakePaymentGateway::fakePayment('pay_return_1', 'captured', $payment->amount_minor);

    $this->actingAs($booking->student)
        ->get(route('payments.return', $payment))
        ->assertRedirect(route('student.bookings.show', $booking))
        ->assertSessionHas('status', 'booking-confirmed');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Captured)
        ->and($booking->refresh()->status)->toBe(BookingStatus::Confirmed);
});

it('accepts the provider posting the return callback', function () {
    $booking = ownedBooking();
    $payment = app(PaymentService::class)->startCheckout($booking);

    $payment->forceFill(['gateway_payment_id' => 'pay_return_2'])->save();
    FakePaymentGateway::fakePayment('pay_return_2', 'captured', $payment->amount_minor);

    $this->actingAs($booking->student)
        ->post(route('payments.return', $payment))
        ->assertRedirect(route('student.bookings.show', $booking));

    expect($payment->refresh()->status)->toBe(PaymentStatus::Captured);
});

it('leaves the booking on hold while the gateway says nothing happened', function () {
    $booking = ownedBooking();
    $payment = app(PaymentService::class)->startCheckout($booking);

    $payment->forceFill(['gateway_payment_id' => 'pay_still_open'])->save();

    FakePaymentGateway::fakePayment('pay_still_open', 'created', $payment->amount_minor);

    $this->actingAs($booking->student)
        ->get(route('payments.return', $payment))
        ->assertSessionHas('status', 'payment-pending');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Created)
        ->and($booking->refresh()->status)->toBe(BookingStatus::PendingPayment);
});

it('does not leak one student payment to another', function () {
    $booking = ownedBooking();
    $payment = app(PaymentService::class)->startCheckout($booking);
    $stranger = User::factory()->student()->onboarded()->create();

    $this->actingAs($stranger)
        ->get(route('payments.return', $payment))
        ->assertStatus(403);
});

it('reconciles stuck orders with the gateway', function () {
    $booking = ownedBooking();
    $payment = app(PaymentService::class)->startCheckout($booking);

    $payment->forceFill([
        'gateway_payment_id' => 'pay_stuck_1',
        'created_at' => now()->subHour(),
    ])->save();

    FakePaymentGateway::fakePayment('pay_stuck_1', 'captured', $payment->amount_minor);

    $this->artisan('payments:reconcile')->assertSuccessful();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Captured)
        ->and($booking->refresh()->status)->toBe(BookingStatus::Confirmed);
});

it('reconciles a payment the gateway refused', function () {
    $booking = ownedBooking();
    $payment = app(PaymentService::class)->startCheckout($booking);

    $payment->forceFill([
        'gateway_payment_id' => 'pay_stuck_2',
        'created_at' => now()->subHour(),
    ])->save();

    FakePaymentGateway::fakePayment('pay_stuck_2', 'failed', $payment->amount_minor);

    $this->artisan('payments:reconcile')->assertSuccessful();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Failed)
        ->and($booking->refresh()->status)->toBe(BookingStatus::PendingPayment);
});

it('leaves fresh orders and orders with no attempt alone', function () {
    $booking = ownedBooking();
    $fresh = app(PaymentService::class)->startCheckout($booking);

    $stale = Payment::factory()->pending()->create([
        'booking_id' => $booking->id,
        'student_id' => $booking->student_id,
        'created_at' => now()->subDay(),
    ]);

    $this->artisan('payments:reconcile')->assertSuccessful();

    expect($fresh->refresh()->status)->toBe(PaymentStatus::Created)
        ->and($stale->refresh()->status)->toBe(PaymentStatus::Created);
});

it('honours the minutes option', function () {
    $booking = ownedBooking();
    $payment = app(PaymentService::class)->startCheckout($booking);

    $payment->forceFill([
        'gateway_payment_id' => 'pay_recent',
        'created_at' => now()->subMinutes(5),
    ])->save();

    FakePaymentGateway::fakePayment('pay_recent', 'captured', $payment->amount_minor);

    $this->artisan('payments:reconcile --minutes=30')->assertSuccessful();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Created);
});
