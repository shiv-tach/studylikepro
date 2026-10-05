<?php

use App\Enums\BookingStatus;
use App\Enums\EarningStatus;
use App\Enums\PaymentStatus;
use App\Enums\RefundStatus;
use App\Models\Refund;
use App\Models\User;
use App\Notifications\LessonRefundProcessed;
use App\Services\Payments\PaymentService;
use App\Services\Payments\RefundService;
use App\Services\PlatformSettings;
use Illuminate\Support\Facades\Notification;

it('refunds a paid lesson in full when the student cancels outside the window', function () {
    Notification::fake();

    $scenario = paidBookingScenario(priceMinor: 100000, hoursAhead: 72);

    $this->actingAs($scenario['student'])
        ->post(route('student.bookings.cancel', $scenario['booking']))
        ->assertSessionHasNoErrors();

    $refund = Refund::query()->firstOrFail();

    expect($refund->amount_minor)->toBe(100000)
        ->and($refund->percent)->toBe(100)
        ->and($refund->status)->toBe(RefundStatus::Processed)
        ->and($refund->initiated_by)->toBe(Refund::INITIATED_BY_STUDENT)
        ->and($refund->gateway_refund_id)->toStartWith('rfnd_fake_')
        ->and($scenario['payment']->refresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($scenario['earning']->refresh()->status)->toBe(EarningStatus::Reversed);

    Notification::assertSentTo($scenario['student'], LessonRefundProcessed::class);
    Notification::assertSentTo($scenario['teacherUser'], LessonRefundProcessed::class);
});

it('refuses a student cancellation inside the window, so nothing is refunded', function () {
    Notification::fake();

    $scenario = paidBookingScenario(hoursAhead: 2);

    $this->actingAs($scenario['student'])
        ->post(route('student.bookings.cancel', $scenario['booking']))
        ->assertStatus(403);

    expect(Refund::query()->count())->toBe(0)
        ->and($scenario['payment']->refresh()->status)->toBe(PaymentStatus::Captured)
        ->and($scenario['booking']->refresh()->status)->toBe(BookingStatus::Confirmed);
});

it('refunds in full when the teacher cancels inside the window', function () {
    Notification::fake();

    $scenario = paidBookingScenario(priceMinor: 60000, hoursAhead: 2);

    $this->actingAs($scenario['teacherUser'])
        ->post(route('teacher.bookings.cancel', $scenario['booking']), ['reason' => 'Family emergency'])
        ->assertSessionHasNoErrors();

    $refund = Refund::query()->firstOrFail();

    expect($refund->amount_minor)->toBe(60000)
        ->and($refund->initiated_by)->toBe(Refund::INITIATED_BY_TEACHER)
        ->and($scenario['payment']->refresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($scenario['earning']->refresh()->status)->toBe(EarningStatus::Reversed);
});

it('refunds when an admin cancels from the console', function () {
    Notification::fake();

    $scenario = paidBookingScenario();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.bookings.cancel', $scenario['booking']), ['reason' => 'Duplicate payment'])
        ->assertSessionHasNoErrors();

    expect(Refund::query()->firstOrFail()->initiated_by)->toBe(Refund::INITIATED_BY_ADMIN)
        ->and($scenario['payment']->refresh()->status)->toBe(PaymentStatus::Refunded);
});

it('lets an admin refund a payment partially from the payments console', function () {
    Notification::fake();

    $scenario = paidBookingScenario(priceMinor: 100000);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.payments.refund', $scenario['payment']), [
            'percent' => 50,
            'reason' => 'Lesson cut short.',
        ])
        ->assertRedirect(route('admin.payments.show', $scenario['payment']))
        ->assertSessionHas('status', 'refund-issued');

    $refund = Refund::query()->firstOrFail();
    $earning = $scenario['earning']->refresh();

    expect($refund->amount_minor)->toBe(50000)
        ->and($refund->percent)->toBe(50)
        ->and($scenario['payment']->refresh()->status)->toBe(PaymentStatus::PartiallyRefunded)
        ->and($earning->reversed_minor)->toBe(42500)
        ->and($earning->netMinor())->toBe(42500);
});

it('has nothing to refund for a hold that was never paid', function () {
    Notification::fake();

    $booking = ownedBooking();

    $this->actingAs($booking->student)
        ->post(route('student.bookings.cancel', $booking))
        ->assertSessionHasNoErrors();

    expect(Refund::query()->count())->toBe(0)
        ->and($booking->refresh()->status)->toBe(BookingStatus::Cancelled);
});

it('never pays the same money back twice', function () {
    Notification::fake();

    $scenario = paidBookingScenario();
    $refunds = app(RefundService::class);

    $first = $refunds->refund($scenario['payment'], 100, Refund::INITIATED_BY_ADMIN, 'Goodwill');
    $second = $refunds->refund($scenario['payment']->refresh(), 100, Refund::INITIATED_BY_ADMIN, 'Again');

    expect($first)->not->toBeNull()
        ->and($second)->toBeNull()
        ->and(Refund::query()->count())->toBe(1)
        ->and($scenario['payment']->refresh()->status)->toBe(PaymentStatus::Refunded);
});

it('refuses to refund an unpaid payment', function () {
    $booking = ownedBooking();
    $payment = app(PaymentService::class)->startCheckout($booking);

    expect(app(RefundService::class)->refund($payment, 100, Refund::INITIATED_BY_ADMIN))->toBeNull()
        ->and(Refund::query()->count())->toBe(0);
});

it('treats a zero percent refund as nothing to do', function () {
    $scenario = paidBookingScenario();

    expect(app(RefundService::class)->refund($scenario['payment'], 0, Refund::INITIATED_BY_ADMIN))->toBeNull()
        ->and(Refund::query()->count())->toBe(0);
});

it('clamps a refund to what is still left on the payment', function () {
    Notification::fake();

    $scenario = paidBookingScenario(priceMinor: 100000);
    $refunds = app(RefundService::class);

    $refunds->refund($scenario['payment'], 50, Refund::INITIATED_BY_ADMIN);
    $second = $refunds->refund($scenario['payment']->refresh(), 100, Refund::INITIATED_BY_ADMIN);

    expect($second->amount_minor)->toBe(50000)
        ->and($scenario['payment']->refresh()->refundedMinor())->toBe(100000)
        ->and($scenario['payment']->status)->toBe(PaymentStatus::Refunded)
        ->and($scenario['earning']->refresh()->status)->toBe(EarningStatus::Reversed);
});

it('uses the configured refund percentages from platform settings', function () {
    Notification::fake();

    app(PlatformSettings::class)->set('refund_teacher_percent', 50);

    $scenario = paidBookingScenario(priceMinor: 100000);

    $this->actingAs($scenario['teacherUser'])
        ->post(route('teacher.bookings.cancel', $scenario['booking']))
        ->assertSessionHasNoErrors();

    expect(Refund::query()->firstOrFail()->amount_minor)->toBe(50000)
        ->and($scenario['payment']->refresh()->status)->toBe(PaymentStatus::PartiallyRefunded);
});
