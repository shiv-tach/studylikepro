<?php

use App\Enums\BookingStatus;
use App\Enums\EarningStatus;
use App\Models\Booking;
use App\Models\Subject;
use App\Models\TeacherAvailabilitySlot;
use App\Models\TeacherEarning;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Services\BookingService;
use App\Services\BookingTransitionService;
use App\Services\Payments\EarningsService;
use App\Services\Payments\PaymentService;
use App\Services\PlatformSettings;
use App\Support\BookingDraft;
use Carbon\CarbonImmutable;

it('keeps the platform fee and the teacher payout adding up to the price', function () {
    $settings = app(PlatformSettings::class);

    foreach ([1, 7, 99, 525, 3333, 52500, 99999, 123456] as $price) {
        $fees = app(BookingService::class)->feeBreakdown($price);

        expect($fees['platform_fee_minor'] + $fees['teacher_payout_minor'])->toBe($price)
            ->and($fees['commission_percent'])->toBe($settings->int('commission_percent'))
            ->and(abs($fees['platform_fee_minor'] - $price * 0.15))->toBeLessThan(1);
    }
});

it('snapshots the commission rate on the booking when the slot is reserved', function () {
    app(PlatformSettings::class)->set('commission_percent', 20);

    $subject = Subject::factory()->create(['name' => 'Mathematics', 'slug' => 'mathematics']);
    $teacherUser = User::factory()->teacher()->create();
    $teacher = TeacherProfile::factory()->approved()->create([
        'user_id' => $teacherUser->id,
        'timezone' => 'UTC',
        'hourly_rate_minor' => 50000,
        'lesson_duration_minutes' => 60,
    ]);
    $teacher->subjects()->attach($subject->id, ['grade_levels' => [(string) gradeId(11)]]);

    $day = CarbonImmutable::now('UTC')->addDays(2)->startOfDay();
    TeacherAvailabilitySlot::factory()->on($day->dayOfWeek, '18:00', '20:00')->create(['teacher_profile_id' => $teacher->id]);

    $student = User::factory()->student()->onboarded()->create();

    $booking = app(BookingService::class)->reserve(new BookingDraft(
        student: $student,
        teacher: $teacher,
        startsAt: $day->setTime(18, 0),
        endsAt: $day->setTime(19, 0),
        subject: $subject,
    ));

    expect($booking->commission_percent)->toBe(20)
        ->and($booking->price_minor)->toBe(50000)
        ->and($booking->platform_fee_minor)->toBe(10000)
        ->and($booking->teacher_payout_minor)->toBe(40000);

    // The snapshot is what the money is settled against, even if the rate moves later.
    app(PlatformSettings::class)->set('commission_percent', 10);

    expect($booking->fresh()->commission_percent)->toBe(20)
        ->and(app(BookingService::class)->feeBreakdown($booking->price_minor)['commission_percent'])->toBe(10);
});

it('records a pending earning as soon as the payment is captured', function () {
    $booking = ownedBooking();
    $payment = app(PaymentService::class)->startCheckout($booking);

    app(PaymentService::class)->capture($payment, 'pay_test_1', 'card');

    $earning = $booking->fresh()->earning;

    expect($earning->status)->toBe(EarningStatus::Pending)
        ->and($earning->amount_minor)->toBe($booking->teacher_payout_minor)
        ->and($earning->teacher_profile_id)->toBe($booking->teacher_profile_id)
        ->and($earning->netMinor())->toBe($booking->teacher_payout_minor);
});

it('re-capturing the same booking does not duplicate the ledger', function () {
    $booking = ownedBooking();
    $payment = app(PaymentService::class)->startCheckout($booking);

    $payments = app(PaymentService::class);
    $payments->capture($payment, 'pay_test_1', 'card');
    $payments->capture($payment->refresh(), 'pay_test_1', 'card');

    expect(TeacherEarning::query()->count())->toBe(1);
});

it('turns a pending earning into an available one when the lesson is delivered', function () {
    $scenario = paidBookingScenario();

    expect($scenario['earning']->status)->toBe(EarningStatus::Pending);

    $scenario['booking']->forceFill([
        'status' => BookingStatus::InProgress,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->subMinutes(10),
    ])->save();

    app(BookingTransitionService::class)->complete($scenario['booking']);

    $earning = $scenario['earning']->refresh();

    expect($earning->status)->toBe(EarningStatus::Eligible)
        ->and($earning->available_at->lessThanOrEqualTo(now()))->toBeTrue();
});

it('makes the earning available when the auto-completion sweep closes the lesson', function () {
    $scenario = paidBookingScenario();

    $scenario['booking']->forceFill([
        'status' => BookingStatus::InProgress,
        'starts_at' => now()->subHours(2),
        'ends_at' => now()->subMinutes(30),
    ])->save();

    $this->artisan('studylikepro:complete-lessons')->assertSuccessful();

    expect($scenario['earning']->refresh()->status)->toBe(EarningStatus::Eligible)
        ->and($scenario['booking']->refresh()->status)->toBe(BookingStatus::Completed);
});

it('keeps the earning pending until the lesson actually happens', function () {
    $scenario = paidBookingScenario();

    $this->artisan('studylikepro:complete-lessons')->assertSuccessful();

    expect($scenario['earning']->refresh()->status)->toBe(EarningStatus::Pending)
        ->and($scenario['booking']->refresh()->status)->toBe(BookingStatus::Confirmed);
});

it('reports what a teacher has earned, pending and been paid', function () {
    $scenario = paidBookingScenario(priceMinor: 100000);

    $earnings = app(EarningsService::class);
    $teacher = $scenario['teacher'];

    $pending = $earnings->totalsFor($teacher);

    expect($pending['pending'])->toBe(85000)
        ->and($pending['available'])->toBe(0)
        ->and($pending['paid'])->toBe(0)
        ->and($pending['lifetime'])->toBe(85000);

    $scenario['earning']->forceFill(['status' => EarningStatus::Eligible])->save();

    $available = $earnings->totalsFor($teacher);

    expect($available['available'])->toBe(85000)
        ->and($available['pending'])->toBe(0);

    $scenario['earning']->forceFill([
        'status' => EarningStatus::Paid,
        'paid_at' => now(),
    ])->save();

    $paid = $earnings->totalsFor($teacher);

    expect($paid['paid'])->toBe(85000)
        ->and($paid['available'])->toBe(0);
});

it('never counts a reversed earning as money the teacher can spend', function () {
    $scenario = paidBookingScenario();

    $scenario['earning']->forceFill(['status' => EarningStatus::Eligible])->save();

    app(EarningsService::class)->reverseForRefund($scenario['booking'], 100);

    $totals = app(EarningsService::class)->totalsFor($scenario['teacher']);

    expect($scenario['earning']->refresh()->status)->toBe(EarningStatus::Reversed)
        ->and($scenario['earning']->netMinor())->toBe(0)
        ->and($totals['available'])->toBe(0)
        ->and($totals['reversed'])->toBe($scenario['earning']->amount_minor)
        ->and($totals['lifetime'])->toBe($scenario['earning']->amount_minor);
});

it('reduces the earning by half when half the lesson is refunded', function () {
    $scenario = paidBookingScenario(priceMinor: 100000);
    $scenario['earning']->forceFill(['status' => EarningStatus::Eligible])->save();

    app(EarningsService::class)->reverseForRefund($scenario['booking'], 50);

    $earning = $scenario['earning']->refresh();

    expect($earning->amount_minor)->toBe(85000)
        ->and($earning->reversed_minor)->toBe(42500)
        ->and($earning->netMinor())->toBe(42500)
        ->and($earning->status)->toBe(EarningStatus::Eligible)
        ->and(app(EarningsService::class)->totalsFor($scenario['teacher'])['available'])->toBe(42500);
});

it('shows the fee split on the teacher booking page', function () {
    $scenario = paidBookingScenario(priceMinor: 100000);

    $this->actingAs($scenario['teacherUser'])
        ->get(route('teacher.bookings.show', $scenario['booking']))
        ->assertOk()
        ->assertSee('1,000')
        ->assertSee('850');
});

it('ignores a booking that has no payment yet', function () {
    $booking = Booking::factory()->hold()->create();

    expect(app(PaymentService::class)->capturedFor($booking))->toBeNull();
});

it('makes an earning payable straight away when the payment lands after delivery', function () {
    $scenario = paidBookingScenario();
    $booking = $scenario['booking'];

    $booking->forceFill([
        'status' => BookingStatus::Completed,
        'starts_at' => now()->subDay(),
        'ends_at' => now()->subDay()->addHour(),
        'completed_at' => now()->subDay(),
    ])->save();

    $earning = app(EarningsService::class)->recordForBooking($booking->fresh(), $scenario['payment']);

    expect($earning->status)->toBe(EarningStatus::Eligible)
        ->and($earning->netMinor())->toBe($booking->teacher_payout_minor);
});
