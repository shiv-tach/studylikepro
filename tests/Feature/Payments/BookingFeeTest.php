<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\BookingFeePromotion;
use App\Models\Payment;
use App\Models\PlatformSetting;
use App\Models\User;
use App\Services\BookingFeeService;
use App\Services\Payments\PaymentService;
use App\Services\PlatformSettings;

it('charges the default booking fee on top of the lesson price', function () {
    expect(app(PlatformSettings::class)->int('booking_fee_minor'))->toBe(10000);

    $booking = reserveFeeBooking(bookingFeeScenario());

    expect($booking->price_minor)->toBe(60000)
        ->and($booking->booking_fee_minor)->toBe(10000)
        ->and($booking->booking_fee_discount_minor)->toBe(0)
        ->and($booking->netBookingFeeMinor())->toBe(10000)
        ->and($booking->totalMinor())->toBe(70000)
        // The fee is extra platform revenue; the payout is settled on the price.
        ->and($booking->platform_fee_minor)->toBe(9000)
        ->and($booking->teacher_payout_minor)->toBe(51000);
});

it('keeps the fee snapshot when the marketplace fee changes later', function () {
    $booking = reserveFeeBooking(bookingFeeScenario());

    app(PlatformSettings::class)->set('booking_fee_minor', 25000);

    expect($booking->fresh()->booking_fee_minor)->toBe(10000)
        ->and($booking->fresh()->totalMinor())->toBe(70000)
        ->and(app(BookingFeeService::class)->netMinor())->toBe(25000);
});

it('charges no fee when the marketplace fee is zero', function () {
    app(PlatformSettings::class)->set('booking_fee_minor', 0);

    $booking = reserveFeeBooking(bookingFeeScenario());

    expect($booking->booking_fee_minor)->toBe(0)
        ->and($booking->totalMinor())->toBe(60000);
});

it('waives the booking fee while a free-booking offer is running', function () {
    BookingFeePromotion::factory()->create(['name' => 'Free booking weekend']);

    $booking = reserveFeeBooking(bookingFeeScenario());

    expect($booking->booking_fee_minor)->toBe(10000)
        ->and($booking->booking_fee_discount_minor)->toBe(10000)
        ->and($booking->netBookingFeeMinor())->toBe(0)
        ->and($booking->totalMinor())->toBe(60000)
        ->and($booking->bookingFeePromotion->name)->toBe('Free booking weekend');
});

it('discounts the booking fee by a percentage offer', function () {
    BookingFeePromotion::factory()->percent(50)->create(['name' => 'Half-price fee']);

    $booking = reserveFeeBooking(bookingFeeScenario());

    expect($booking->booking_fee_discount_minor)->toBe(5000)
        ->and($booking->netBookingFeeMinor())->toBe(5000)
        ->and($booking->totalMinor())->toBe(65000);
});

it('discounts the booking fee by a fixed amount and caps it at the fee', function () {
    BookingFeePromotion::factory()->fixed(4000)->create(['name' => 'Rs 40 off']);

    $scenario = bookingFeeScenario();

    expect(reserveFeeBooking($scenario, hour: 18)->totalMinor())->toBe(66000);

    BookingFeePromotion::query()->delete();
    BookingFeePromotion::factory()->fixed(50000)->create(['name' => 'Rs 500 off']);

    expect(reserveFeeBooking($scenario, hour: 19)->netBookingFeeMinor())->toBe(0);
});

it('ignores paused, expired and upcoming offers', function () {
    BookingFeePromotion::factory()->inactive()->create(['name' => 'Paused']);
    BookingFeePromotion::factory()->expired()->create(['name' => 'Last week']);
    BookingFeePromotion::factory()->upcoming()->create(['name' => 'Next week']);

    $quote = app(BookingFeeService::class)->quote();

    expect($quote['booking_fee_minor'])->toBe(10000)
        ->and($quote['discount_minor'])->toBe(0)
        ->and($quote['net_minor'])->toBe(10000)
        ->and($quote['promotion'])->toBeNull();
});

it('applies the most generous running offer', function () {
    BookingFeePromotion::factory()->percent(30)->create(['name' => 'Thirty percent']);
    BookingFeePromotion::factory()->fixed(4000)->create(['name' => 'Rs 40 off']);

    $quote = app(BookingFeeService::class)->quote();

    expect($quote['discount_minor'])->toBe(4000)
        ->and($quote['net_minor'])->toBe(6000)
        ->and($quote['promotion']->name)->toBe('Rs 40 off');
});

it('charges the lesson plus the booking fee at checkout', function () {
    $booking = reserveFeeBooking(bookingFeeScenario());

    $payment = app(PaymentService::class)->startCheckout($booking);

    expect($payment->amount_minor)->toBe(70000)
        ->and($payment->currency)->toBe('LKR');

    // A second attempt reuses the same order instead of re-pricing the hold.
    expect(app(PaymentService::class)->startCheckout($booking)->id)->toBe($payment->id);

    app(PaymentService::class)->capture($payment, 'pay_fee_1', 'card');

    expect($payment->fresh()->amount_minor)->toBe(70000)
        ->and($booking->fresh()->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->fresh()->earning->amount_minor)->toBe(51000);
});

it('charges only the lesson when a waiver is running', function () {
    BookingFeePromotion::factory()->create(['name' => 'Free booking weekend']);

    $booking = reserveFeeBooking(bookingFeeScenario());
    $payment = app(PaymentService::class)->startCheckout($booking);

    expect($payment->amount_minor)->toBe(60000)
        ->and($booking->totalMinor())->toBe(60000);
});

it('shows the booking fee on the booking, checkout and receipt pages', function () {
    $scenario = bookingFeeScenario();

    $this->actingAs($scenario['student'])
        ->get(route('student.bookings.create', $scenario['teacher']))
        ->assertOk()
        ->assertSee('Booking fee')
        ->assertSee('700');

    $this->actingAs($scenario['student'])
        ->post(route('student.bookings.store', $scenario['teacher']), [
            'subject_id' => $scenario['subject']->id,
            'duration' => 60,
            'starts_at' => $scenario['slot']->toIso8601String(),
        ])
        ->assertSessionHasNoErrors();

    $booking = Booking::query()->latest('id')->firstOrFail();

    $this->actingAs($scenario['student'])
        ->get(route('student.bookings.show', $booking))
        ->assertOk()
        ->assertSee('Platform booking fee')
        ->assertSee('700');

    $this->actingAs($scenario['student'])
        ->post(route('student.bookings.checkout', $booking))
        ->assertRedirect();

    $payment = $booking->payments()->latest('id')->firstOrFail();

    $this->actingAs($scenario['student'])
        ->get(route('student.payments.checkout', $payment))
        ->assertOk()
        ->assertSee('Platform booking fee')
        ->assertSee('700');

    app(PaymentService::class)->capture($payment, 'pay_fee_receipt', 'card');

    $this->actingAs($scenario['student'])
        ->get(route('receipts.show', $booking))
        ->assertOk()
        ->assertSee('Lesson fee')
        ->assertSee('600')
        ->assertSee('Platform booking fee')
        ->assertSee('700');
});

it('lists the fee on the receipt as a discount when an offer applied', function () {
    BookingFeePromotion::factory()->create(['name' => 'Free booking weekend']);

    $booking = reserveFeeBooking(bookingFeeScenario());
    $payment = app(PaymentService::class)->startCheckout($booking);

    app(PaymentService::class)->capture($payment, 'pay_fee_free', 'card');

    $this->actingAs($booking->student)
        ->get(route('receipts.show', $booking))
        ->assertOk()
        ->assertSee('Platform booking fee')
        ->assertSee('Special offer')
        ->assertSee('Free booking weekend')
        ->assertSee('600');
});

it('lets an admin retune the booking fee from the settings console', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.settings.edit'))
        ->assertOk()
        ->assertSee('name="booking_fee_minor"', false)
        ->assertSee('value="100.00"', false);

    $this->actingAs($admin)
        ->put(route('admin.settings.update'), ['booking_fee_minor' => 250])
        ->assertRedirect(route('admin.settings.edit'))
        ->assertSessionHas('status', 'settings-saved');

    expect(platform_settings()->int('booking_fee_minor'))->toBe(25000)
        ->and(PlatformSetting::query()->where('key', 'booking_fee_minor')->value('value'))->toBe('25000');

    $booking = reserveFeeBooking(bookingFeeScenario());

    expect($booking->booking_fee_minor)->toBe(25000)
        ->and($booking->totalMinor())->toBe(85000);
});

it('rejects a negative booking fee', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('admin.settings.update'), ['booking_fee_minor' => -5])
        ->assertSessionHasErrors('booking_fee_minor');

    expect(platform_settings()->int('booking_fee_minor'))->toBe(10000);
});

it('leaves bookings without a fee reading exactly as before', function () {
    $booking = Booking::factory()->create(['price_minor' => 70000]);
    $payment = Payment::factory()->create(['booking_id' => $booking->id]);

    expect($booking->netBookingFeeMinor())->toBe(0)
        ->and($booking->hasBookingFeeDiscount())->toBeFalse()
        ->and($booking->totalMinor())->toBe(70000)
        ->and($payment->amount_minor)->toBe(70000);
});
