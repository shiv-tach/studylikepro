<?php

use App\Enums\BookingStatus;
use App\Enums\EarningStatus;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Enums\ServiceType;
use App\Events\BookingCompleted;
use App\Models\Booking;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Payment;
use App\Models\ServiceOffering;
use App\Models\ServicePurchase;
use App\Models\Subject;
use App\Models\TeacherAvailabilitySlot;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Services\Payments\PaymentService;
use App\Services\PurchaseService;
use App\Services\SessionSchedulingService;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * @return array{student: User, teacher: TeacherProfile, offering: ServiceOffering, subject: Subject, lesson: Lesson}
 */
function serviceScenario(string $type = ServiceType::Lesson->value, array $overrides = []): array
{
    $student = User::factory()->student()->onboarded()->create();
    $teacherUser = User::factory()->teacher()->create();
    $teacher = TeacherProfile::factory()->approved()->create(['user_id' => $teacherUser->id]);

    $subject = Subject::factory()->create();
    $grade = Grade::factory()->create();
    $lesson = Lesson::factory()->create(['subject_id' => $subject->id, 'grade_id' => $grade->id]);

    $offering = ServiceOffering::factory()
        ->forType(ServiceType::from($type))
        ->create([
            'teacher_profile_id' => $teacher->id,
            'subject_id' => $subject->id,
            'lesson_id' => in_array($type, [ServiceType::Summary->value, ServiceType::Lesson->value], true)
                ? $lesson->id
                : null,
            ...$overrides,
        ]);

    // A full-day weekday slot so the scheduling engine always has room.
    TeacherAvailabilitySlot::factory()->create([
        'teacher_profile_id' => $teacher->id,
        'day_of_week' => 1,
        'start_minute' => 0,
        'end_minute' => 1440,
    ]);

    return compact('student', 'teacher', 'offering', 'subject', 'lesson');
}

function capturePurchase(ServicePurchase $purchase): Payment
{
    $payment = app(PurchaseService::class)->startCheckout($purchase);

    return app(PaymentService::class)->capture($payment, 'pay_fake_test', 'card', $payment->amount_minor);
}

it('creates a pending purchase from an offering with money snapshots', function () {
    ['student' => $student, 'offering' => $offering] = serviceScenario();

    $this->actingAs($student)
        ->post(route('student.purchases.store', $offering))
        ->assertRedirect()
        ->assertSessionHas('status', 'purchase-created');

    $purchase = ServicePurchase::firstOrFail();

    expect($purchase->status)->toBe(PurchaseStatus::PendingPayment)
        ->and($purchase->price_minor)->toBe($offering->price_minor)
        ->and($purchase->student_id)->toBe($student->id)
        ->and($purchase->teacher_profile_id)->toBe($offering->teacher_profile_id)
        ->and($purchase->platform_fee_minor)->toBeGreaterThan(0);
});

it('activates a purchase when the payment is captured and opens a pending earning', function () {
    ['student' => $student, 'offering' => $offering] = serviceScenario();

    $purchase = app(PurchaseService::class)->create($offering, $student);

    $payment = capturePurchase($purchase);

    expect($payment->status)->toBe(PaymentStatus::Captured)
        ->and($payment->service_purchase_id)->toBe($purchase->id)
        ->and($purchase->fresh()->status)->toBe(PurchaseStatus::Active)
        ->and($purchase->fresh()->paid_at)->not->toBeNull()
        ->and($purchase->fresh()->earning->status)->toBe(EarningStatus::Pending);
});

it('wires the demo payment buttons on the purchase checkout page', function () {
    ['student' => $student, 'offering' => $offering] = serviceScenario();

    $purchase = app(PurchaseService::class)->create($offering, $student);
    $payment = app(PurchaseService::class)->startCheckout($purchase);

    $this->actingAs($student)
        ->get(route('student.purchases.checkout.show', $payment))
        ->assertOk()
        ->assertSee('data-demo-payment', false)
        ->assertSee('form[data-demo-payment]', false);
});

it('shows a service-purchase earning on the teacher ledger', function () {
    ['student' => $student, 'teacher' => $teacher, 'offering' => $offering, 'lesson' => $lesson] = serviceScenario();

    $purchase = app(PurchaseService::class)->create($offering, $student);
    capturePurchase($purchase);

    $this->actingAs($teacher->user)
        ->get(route('teacher.earnings.index'))
        ->assertOk()
        ->assertSee($lesson->name)
        ->assertSee($student->name);
});

it('books a session against a plan and tracks the remaining balance', function () {
    ['student' => $student, 'offering' => $offering] = serviceScenario(
        overrides: [
            'allocation_minutes' => 240,
            'default_session_minutes' => 60,
            'max_sessions' => 4,
        ]
    );

    $purchase = app(PurchaseService::class)->create($offering, $student);
    capturePurchase($purchase);
    $purchase->refresh();

    $startsAt = CarbonImmutable::now()->next(CarbonImmutable::MONDAY)->setTime(9, 0)->utc();

    $booking = app(SessionSchedulingService::class)->reserve($purchase, $startsAt, 60);

    expect($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->isPurchaseSession())->toBeTrue()
        ->and($booking->price_minor)->toBe(0)
        ->and($purchase->fresh()->remainingMinutes())->toBe(180);
});

it('blocks sessions longer than the remaining plan balance', function () {
    ['student' => $student, 'offering' => $offering] = serviceScenario(
        overrides: ['allocation_minutes' => 60, 'max_sessions' => 1]
    );

    $purchase = app(PurchaseService::class)->create($offering, $student);
    capturePurchase($purchase);
    $purchase->refresh();

    $startsAt = CarbonImmutable::now()->next(CarbonImmutable::MONDAY)->setTime(9, 0)->utc();

    app(SessionSchedulingService::class)->reserve($purchase, $startsAt, 60);

    $this->expectException(ValidationException::class);

    app(SessionSchedulingService::class)->reserve(
        $purchase->fresh(),
        $startsAt->addHours(2),
        60,
    );
});

it('completes the plan once the allocation is consumed', function () {
    ['student' => $student, 'offering' => $offering] = serviceScenario(
        overrides: ['allocation_minutes' => 60, 'max_sessions' => 1]
    );

    $purchase = app(PurchaseService::class)->create($offering, $student);
    capturePurchase($purchase);
    $purchase->refresh();

    $startsAt = CarbonImmutable::now()->next(CarbonImmutable::MONDAY)->setTime(9, 0)->utc();

    $booking = app(SessionSchedulingService::class)->reserve($purchase, $startsAt, 60);

    $booking->forceFill(['status' => BookingStatus::Completed, 'completed_at' => now()])->save();

    BookingCompleted::dispatch($booking);

    $purchase->refresh();

    expect($purchase->status)->toBe(PurchaseStatus::Completed)
        ->and($purchase->earning->status)->toBe(EarningStatus::Eligible);
});

it('refunds the unused balance prorated when a plan expires', function () {
    config(['studylikepro.services.refund_unused_balance_on_expiry' => true]);

    ['student' => $student, 'offering' => $offering] = serviceScenario(
        overrides: ['allocation_minutes' => 120, 'price_minor' => 12000]
    );

    $purchase = app(PurchaseService::class)->create($offering, $student);
    $payment = capturePurchase($purchase);
    $purchase->refresh();

    // The student consumed 60 of 120 minutes.
    $startsAt = CarbonImmutable::now()->subDays(2)->setTime(9, 0)->utc();

    Booking::factory()->create([
        'student_id' => $student->id,
        'teacher_profile_id' => $offering->teacher_profile_id,
        'service_purchase_id' => $purchase->id,
        'service_type' => ServiceType::Lesson->value,
        'status' => BookingStatus::Completed,
        'starts_at' => $startsAt,
        'ends_at' => $startsAt->addHour(),
        'price_minor' => 0,
        'commission_percent' => 0,
        'platform_fee_minor' => 0,
        'teacher_payout_minor' => 0,
        'booking_fee_minor' => 0,
        'completed_at' => now(),
    ]);

    $purchase->forceFill(['expires_at' => now()->subMinute()])->save();

    $this->artisan('studylikepro:expire-service-purchases')->assertSuccessful();

    $purchase->refresh();

    expect($purchase->status)->toBe(PurchaseStatus::Expired)
        ->and($payment->refresh()->refunds()->count())->toBe(1)
        ->and($payment->fresh()->refundedMinor())->toBeGreaterThan(0)
        ->and($payment->fresh()->refundedMinor())->toBeLessThan($payment->amount_minor);
});

it('books a Q&A session against a purchased Q&A service', function () {
    ['student' => $student, 'offering' => $offering] = serviceScenario(
        ServiceType::Qna->value,
        overrides: ['default_session_minutes' => 45],
    );

    $purchase = app(PurchaseService::class)->create($offering, $student);
    capturePurchase($purchase);
    $purchase->refresh();

    $startsAt = CarbonImmutable::now()->next(CarbonImmutable::MONDAY)->setTime(9, 0)->utc();

    $booking = app(SessionSchedulingService::class)->reserve($purchase, $startsAt, 45);

    expect($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->service_type)->toBe(ServiceType::Qna->value)
        ->and($booking->subject_id)->toBe($offering->subject_id)
        ->and($booking->lesson_id)->toBeNull()
        ->and($purchase->fresh()->status)->toBe(PurchaseStatus::Active);
});

it('completes a single-session service once its session completes', function () {
    ['student' => $student, 'offering' => $offering] = serviceScenario(
        ServiceType::PaperDiscussion->value,
        overrides: ['default_session_minutes' => 60],
    );

    $purchase = app(PurchaseService::class)->create($offering, $student);
    capturePurchase($purchase);
    $purchase->refresh();

    $startsAt = CarbonImmutable::now()->next(CarbonImmutable::MONDAY)->setTime(9, 0)->utc();

    $booking = app(SessionSchedulingService::class)->reserve($purchase, $startsAt, 60);

    $booking->forceFill(['status' => BookingStatus::Completed, 'completed_at' => now()])->save();

    BookingCompleted::dispatch($booking);

    $purchase->refresh();

    expect($purchase->status)->toBe(PurchaseStatus::Completed)
        ->and($purchase->earning->status)->toBe(EarningStatus::Eligible);
});

it('shows the purchases dashboard with the remaining plan balance', function () {
    ['student' => $student, 'offering' => $offering] = serviceScenario(
        overrides: ['allocation_minutes' => 240]
    );

    $purchase = app(PurchaseService::class)->create($offering, $student);
    capturePurchase($purchase);

    $this->actingAs($student)
        ->get(route('student.purchases.index'))
        ->assertOk()
        ->assertSee('My purchases');

    $this->actingAs($student)
        ->get(route('student.purchases.show', $purchase))
        ->assertOk()
        ->assertSee('240');
});
