<?php

use App\Enums\BookingStatus;
use App\Enums\EarningStatus;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentWebhookEvent;
use App\Models\TeacherEarning;
use App\Models\User;
use App\Services\Payments\FakePaymentGateway;
use App\Services\Payments\PaymentService;

/**
 * @return array{payment: Payment, payload: string, signature: string}
 */
function fakeWebhookPayload(Payment $payment, string $kind = 'capture'): array
{
    $gateway = app(FakePaymentGateway::class);

    [$body, $signature] = $kind === 'capture'
        ? $gateway->localCapturePayload($payment)
        : $gateway->localFailurePayload($payment);

    return ['payment' => $payment, 'payload' => (string) json_encode($body), 'signature' => $signature];
}

it('opens a provider order for a booking hold', function () {
    $booking = ownedBooking();
    $student = $booking->student;

    $this->actingAs($student)
        ->post(route('student.bookings.checkout', $booking))
        ->assertRedirect();

    $payment = $booking->payments()->firstOrFail();

    expect($payment->status)->toBe(PaymentStatus::Created)
        ->and($payment->gateway)->toBe('fake')
        ->and($payment->gateway_order_id)->toStartWith('order_fake_')
        ->and($payment->amount_minor)->toBe($booking->price_minor)
        ->and($payment->student_id)->toBe($student->id)
        ->and($payment->gateway_payment_id)->toBeNull();

    $this->actingAs($student)
        ->get(route('student.payments.checkout', $payment))
        ->assertOk()
        ->assertSee('Demo gateway')
        ->assertSee('Pay ');
});

it('reuses the open order when the student comes back to checkout', function () {
    $booking = ownedBooking();
    $student = $booking->student;

    $this->actingAs($student)->post(route('student.bookings.checkout', $booking));
    $firstId = $booking->payments()->firstOrFail()->id;

    $this->actingAs($student)->post(route('student.bookings.checkout', $booking));

    expect($booking->payments()->count())->toBe(1)
        ->and($booking->payments()->firstOrFail()->id)->toBe($firstId);
});

it('shows the checkout page to the payer and nobody else', function () {
    $booking = ownedBooking();
    $student = $booking->student;

    $this->actingAs($student)->post(route('student.bookings.checkout', $booking));
    $payment = $booking->payments()->firstOrFail();

    $stranger = User::factory()->student()->onboarded()->create();

    $this->actingAs($student)->get(route('student.payments.checkout', $payment))->assertOk();
    $this->actingAs($stranger)->get(route('student.payments.checkout', $payment))->assertStatus(403);
});

it('confirms the booking and opens an earning when a signed capture webhook arrives', function () {
    $booking = ownedBooking();
    $student = $booking->student;

    $payment = app(PaymentService::class)->startCheckout($booking);
    $webhook = fakeWebhookPayload($payment);

    $this->post(route('payments.webhook', 'fake'), [
        'payload' => $webhook['payload'],
        'signature' => $webhook['signature'],
    ])->assertOk()->assertJson(['status' => 'processed']);

    $payment->refresh();
    $booking->refresh();

    expect($payment->status)->toBe(PaymentStatus::Captured)
        ->and($payment->gateway_payment_id)->toBe('pay_fake_'.$payment->id)
        ->and($payment->method)->toBe('upi')
        ->and($payment->captured_at)->not->toBeNull()
        ->and($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->expires_at)->toBeNull();

    $earning = TeacherEarning::query()->where('booking_id', $booking->id)->firstOrFail();

    expect($earning->status)->toBe(EarningStatus::Pending)
        ->and($earning->amount_minor)->toBe($booking->teacher_payout_minor)
        ->and($earning->payment_id)->toBe($payment->id)
        ->and($earning->available_at->timestamp)->toBe(
            $booking->ends_at->copy()->addMinutes((int) config('studylikepro.booking.auto_complete_grace_minutes'))->timestamp
        );

    expect(PaymentWebhookEvent::query()->firstOrFail()->status)->toBe(PaymentWebhookEvent::STATUS_PROCESSED);
});

it('rejects a webhook whose signature does not match', function () {
    $booking = ownedBooking();
    $payment = app(PaymentService::class)->startCheckout($booking);

    $this->post(route('payments.webhook', 'fake'), [
        'payload' => fakeWebhookPayload($payment)['payload'],
        'signature' => 'not-a-real-signature',
    ])->assertStatus(400);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Created)
        ->and($booking->refresh()->status)->toBe(BookingStatus::PendingPayment)
        ->and(PaymentWebhookEvent::query()->count())->toBe(0);
});

it('applies a duplicated delivery only once', function () {
    $booking = ownedBooking();
    $payment = app(PaymentService::class)->startCheckout($booking);
    $webhook = fakeWebhookPayload($payment);

    $this->post(route('payments.webhook', 'fake'), $webhook)->assertOk()->assertJson(['status' => 'processed']);

    $capturedAt = $payment->refresh()->captured_at;

    $this->post(route('payments.webhook', 'fake'), $webhook)->assertOk()->assertJson(['status' => 'duplicate']);

    expect($payment->refresh()->captured_at->timestamp)->toBe($capturedAt->timestamp)
        ->and(Payment::query()->count())->toBe(1)
        ->and(TeacherEarning::query()->count())->toBe(1)
        ->and(PaymentWebhookEvent::query()->count())->toBe(1);
});

it('rejects callbacks for a gateway it does not speak', function () {
    $this->post(route('payments.webhook', 'stripe'), ['payload' => '{}', 'signature' => 'x'])
        ->assertStatus(404);
});

it('marks the payment failed but leaves the hold running when the gateway declines', function () {
    $booking = ownedBooking();
    $payment = app(PaymentService::class)->startCheckout($booking);

    $this->post(route('payments.webhook', 'fake'), fakeWebhookPayload($payment, 'failure'))
        ->assertOk()
        ->assertJson(['status' => 'processed']);

    $payment->refresh();
    $booking->refresh();

    expect($payment->status)->toBe(PaymentStatus::Failed)
        ->and($payment->failure_reason)->toContain('declined')
        ->and($booking->status)->toBe(BookingStatus::PendingPayment)
        ->and($booking->expires_at)->not->toBeNull()
        ->and(TeacherEarning::query()->count())->toBe(0);
});

it('ignores events for payments it has never seen', function () {
    $booking = ownedBooking();
    $payment = app(PaymentService::class)->startCheckout($booking);

    $webhook = fakeWebhookPayload($payment);
    $payload = str_replace('order_fake', 'order_unknown', $webhook['payload']);
    $signature = app(FakePaymentGateway::class)->signPayload($payload);

    $this->post(route('payments.webhook', 'fake'), ['payload' => $payload, 'signature' => $signature])
        ->assertOk()
        ->assertJson(['status' => 'ignored']);

    expect($payment->refresh()->status)->toBe(PaymentStatus::Created)
        ->and(PaymentWebhookEvent::query()->firstOrFail()->status)->toBe(PaymentWebhookEvent::STATUS_IGNORED);
});

it('refuses to open a second checkout for a lesson already paid for', function () {
    $scenario = paidBookingScenario();

    $this->actingAs($scenario['student'])
        ->post(route('student.bookings.checkout', $scenario['booking']))
        ->assertStatus(403);
});
