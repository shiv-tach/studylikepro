<?php

use App\Enums\BookingStatus;
use App\Enums\EarningStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Enums\ServiceType;
use App\Models\BankAccount;
use App\Models\Booking;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Payment;
use App\Models\ServiceOffering;
use App\Models\Subject;
use App\Models\TeacherEarning;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Notifications\ManualPaymentRejected as ManualPaymentRejectedNotification;
use App\Services\Payments\PaymentService;
use App\Services\PurchaseService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

/**
 * A configured bank account; the first one becomes the default in the console.
 */
function manualBankAccount(array $overrides = []): BankAccount
{
    return BankAccount::factory()->create($overrides);
}

/**
 * A pending lesson-plan purchase the student can pay for manually.
 *
 * @return array{student: User, teacher: TeacherProfile, offering: ServiceOffering, purchase: App\Models\ServicePurchase}
 */
function manualPurchaseScenario(): array
{
    $student = User::factory()->student()->onboarded()->create();
    $teacherUser = User::factory()->teacher()->create();
    $teacher = TeacherProfile::factory()->approved()->create(['user_id' => $teacherUser->id]);

    $subject = Subject::factory()->create();
    $grade = Grade::factory()->create();
    $lesson = Lesson::factory()->create(['subject_id' => $subject->id, 'grade_id' => $grade->id]);

    $offering = ServiceOffering::factory()->forType(ServiceType::Lesson)->create([
        'teacher_profile_id' => $teacher->id,
        'subject_id' => $subject->id,
        'lesson_id' => $lesson->id,
    ]);

    $purchase = app(PurchaseService::class)->create($offering, $student);

    return compact('student', 'teacher', 'offering', 'purchase');
}

/**
 * Walk a booking payment through the manual flow up to "awaiting verification".
 */
function submitSlipFor(Booking $booking, PaymentMethod $method = PaymentMethod::OnlineTransfer, string $reference = 'REF-1001'): Payment
{
    $payment = app(PaymentService::class)->startCheckout($booking);

    test()->actingAs($booking->student)
        ->post(route('student.payments.method', $payment), ['method' => $method->value])
        ->assertRedirect();

    test()->actingAs($booking->student)
        ->post(route('student.payments.slip.store', $payment), [
            'slip' => UploadedFile::fake()->image('slip.jpg'),
            'reference' => $reference,
        ])
        ->assertRedirect();

    return $payment->refresh();
}

it('keeps a booking held for at least fifteen minutes when online transfer is picked', function () {
    Storage::fake('local');
    manualBankAccount(['is_default' => true]);

    $booking = ownedBooking();
    $booking->forceFill(['expires_at' => now()->addMinutes(5)])->save();

    $payment = app(PaymentService::class)->startCheckout($booking);

    $this->actingAs($booking->student)
        ->post(route('student.payments.method', $payment), ['method' => PaymentMethod::OnlineTransfer->value])
        ->assertRedirect()
        ->assertSessionHas('status', 'payment-method-selected');

    $payment->refresh();
    $booking->refresh();

    expect($payment->payment_method)->toBe(PaymentMethod::OnlineTransfer)
        ->and($payment->gateway)->toBe('manual')
        ->and($payment->gateway_order_id)->toBeNull()
        ->and($booking->expires_at->greaterThanOrEqualTo(now()->addMinutes(14)))->toBeTrue();
});

it('gives a bank deposit a window measured in hours and never shortens a longer hold', function () {
    Storage::fake('local');
    manualBankAccount();
    platform_settings()->set('bank_deposit_hold_hours', 24);

    $booking = ownedBooking();
    $booking->forceFill(['expires_at' => now()->addMinutes(5)])->save();

    $payment = app(PaymentService::class)->startCheckout($booking);

    $this->actingAs($booking->student)
        ->post(route('student.payments.method', $payment), ['method' => PaymentMethod::BankDeposit->value])
        ->assertRedirect();

    $booking->refresh();
    expect($booking->expires_at->greaterThanOrEqualTo(now()->addHours(23)))->toBeTrue();

    // Picking an online transfer afterwards must not pull the deadline in.
    $this->actingAs($booking->student)
        ->post(route('student.payments.method', $payment), ['method' => PaymentMethod::OnlineTransfer->value])
        ->assertRedirect();

    expect($booking->refresh()->expires_at->greaterThanOrEqualTo(now()->addHours(23)))->toBeTrue();
});

it('stores the uploaded slip and parks the payment for verification', function () {
    Storage::fake('local');
    $account = manualBankAccount(['is_default' => true]);

    $booking = ownedBooking();
    $payment = app(PaymentService::class)->startCheckout($booking);

    $this->actingAs($booking->student)
        ->post(route('student.payments.method', $payment), ['method' => PaymentMethod::BankDeposit->value]);

    $this->actingAs($booking->student)
        ->post(route('student.payments.slip.store', $payment), [
            'slip' => UploadedFile::fake()->image('deposit-slip.jpg'),
            'bank_account_id' => $account->id,
            'reference' => ' DEP-778 ',
        ])
        ->assertRedirect()
        ->assertSessionHas('status', 'payment-slip-submitted');

    $payment->refresh();

    expect($payment->status)->toBe(PaymentStatus::Submitted)
        ->and($payment->bank_account_id)->toBe($account->id)
        ->and($payment->bank_reference)->toBe('DEP-778')
        ->and($payment->slip_original_name)->toBe('deposit-slip.jpg')
        ->and($payment->submitted_at)->not->toBeNull()
        ->and($booking->refresh()->status)->toBe(BookingStatus::PendingPayment);

    Storage::disk('local')->assertExists($payment->slip_path);
});

it('replaces an uploaded slip and removes the previous file', function () {
    Storage::fake('local');
    manualBankAccount();

    $booking = ownedBooking();
    $payment = submitSlipFor($booking);
    $firstPath = $payment->slip_path;

    $this->actingAs($booking->student)
        ->post(route('student.payments.slip.store', $payment), [
            'slip' => UploadedFile::fake()->image('better-slip.png'),
        ])
        ->assertRedirect();

    $payment->refresh();

    expect($payment->status)->toBe(PaymentStatus::Submitted)
        ->and($payment->slip_path)->not->toBe($firstPath);

    Storage::disk('local')->assertMissing($firstPath);
    Storage::disk('local')->assertExists($payment->slip_path);
});

it('confirms the booking and opens the earning when an admin verifies the slip', function () {
    Storage::fake('local');
    manualBankAccount();

    $booking = ownedBooking();
    $payment = submitSlipFor($booking);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.payments.verify', $payment))
        ->assertRedirect(route('admin.payments.show', $payment))
        ->assertSessionHas('status', 'payment-verified');

    $payment->refresh();
    $booking->refresh();

    expect($payment->status)->toBe(PaymentStatus::Captured)
        ->and($payment->verified_by)->toBe($admin->id)
        ->and($payment->captured_at)->not->toBeNull()
        ->and($payment->method)->toBe(PaymentMethod::OnlineTransfer->value)
        ->and($payment->gateway_payment_id)->toBe('manual-'.$payment->id)
        ->and($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->expires_at)->toBeNull();

    $earning = TeacherEarning::query()->where('booking_id', $booking->id)->firstOrFail();

    expect($earning->status)->toBe(EarningStatus::Pending)
        ->and($earning->payment_id)->toBe($payment->id);
});

it('activates a purchase when an admin verifies its slip', function () {
    Storage::fake('local');
    manualBankAccount();

    ['student' => $student, 'purchase' => $purchase] = manualPurchaseScenario();

    $payment = app(PurchaseService::class)->startCheckout($purchase);

    $this->actingAs($student)
        ->post(route('student.payments.method', $payment), ['method' => PaymentMethod::BankDeposit->value]);

    $this->actingAs($student)
        ->post(route('student.payments.slip.store', $payment), [
            'slip' => UploadedFile::fake()->image('slip.jpg'),
        ]);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.payments.verify', $payment->refresh()))
        ->assertRedirect();

    $purchase->refresh();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Captured)
        ->and($purchase->status)->toBe(PurchaseStatus::Active)
        ->and($purchase->paid_at)->not->toBeNull();

    expect(TeacherEarning::query()->where('service_purchase_id', $purchase->id)->exists())->toBeTrue();
});

it('rejects a slip with a reason, notifies the student and allows a resubmission', function () {
    Storage::fake('local');
    Notification::fake();
    manualBankAccount();

    $booking = ownedBooking();
    $payment = submitSlipFor($booking);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.payments.reject', $payment), ['reason' => 'The reference does not match any deposit.'])
        ->assertRedirect(route('admin.payments.show', $payment))
        ->assertSessionHas('status', 'payment-rejected');

    $payment->refresh();

    expect($payment->status)->toBe(PaymentStatus::Rejected)
        ->and($payment->failure_reason)->toBe('The reference does not match any deposit.')
        ->and($payment->verified_by)->toBe($admin->id);

    Notification::assertSentTo($booking->student, ManualPaymentRejectedNotification::class);

    // The student gets another go while the window is still open.
    $this->actingAs($booking->student)
        ->post(route('student.payments.slip.store', $payment), [
            'slip' => UploadedFile::fake()->image('second-slip.jpg'),
        ])
        ->assertRedirect();

    expect($payment->refresh()->status)->toBe(PaymentStatus::Submitted);
});

it('requires a reason before rejecting a slip', function () {
    Storage::fake('local');
    manualBankAccount();

    $payment = submitSlipFor(ownedBooking());

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.payments.reject', $payment), [])
        ->assertSessionHasErrors('reason');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Submitted);
});

it('pauses the hold expiry while a slip is waiting for a decision', function () {
    Storage::fake('local');
    manualBankAccount();

    $booking = ownedBooking();
    $payment = submitSlipFor($booking);

    $booking->forceFill(['expires_at' => now()->subMinute()])->save();

    $this->artisan('studylikepro:expire-holds')->assertSuccessful();

    expect($booking->refresh()->status)->toBe(BookingStatus::PendingPayment)
        ->and($payment->refresh()->status)->toBe(PaymentStatus::Submitted);

    // A control booking without a slip still lapses.
    $other = ownedBooking();
    $other->forceFill(['expires_at' => now()->subMinute()])->save();

    $this->artisan('studylikepro:expire-holds')->assertSuccessful();

    expect($other->refresh()->status)->toBe(BookingStatus::Expired);
});

it('streams a slip only to its owner and to admins', function () {
    Storage::fake('local');
    manualBankAccount();

    $booking = ownedBooking();
    $payment = submitSlipFor($booking);

    $this->actingAs($booking->student)
        ->get(route('student.payments.slip.show', $payment))
        ->assertOk();

    $this->actingAs(User::factory()->student()->onboarded()->create())
        ->get(route('student.payments.slip.show', $payment))
        ->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.payments.slip', $payment))
        ->assertOk();
});

it('refuses the card method while the gateway is not live', function () {
    config(['studylikepro.payments.gateway' => 'razorpay']);
    manualBankAccount();

    $booking = ownedBooking();
    $payment = app(PaymentService::class)->startCheckout($booking);

    $this->actingAs($booking->student)
        ->post(route('student.payments.method', $payment), ['method' => PaymentMethod::Gateway->value])
        ->assertSessionHasErrors('method');

    expect($payment->refresh()->payment_method)->toBeNull()
        ->and($payment->status)->toBe(PaymentStatus::Created);
});

it('shows the method picker with card payments marked as coming soon', function () {
    config(['studylikepro.payments.gateway' => 'razorpay']);
    manualBankAccount(['bank_name' => 'Commercial Bank']);

    $booking = ownedBooking();
    $payment = app(PaymentService::class)->startCheckout($booking);

    $this->actingAs($booking->student)
        ->get(route('student.payments.checkout', $payment))
        ->assertOk()
        ->assertSee('Choose how to pay')
        ->assertSee('Online transfer')
        ->assertSee('Bank deposit')
        ->assertSee('Coming soon');
});

it('shows the bank details and slip form once a manual method is picked', function () {
    Storage::fake('local');
    manualBankAccount(['bank_name' => 'Hatton National Bank', 'account_number' => '9876543210']);

    $booking = ownedBooking();
    $payment = app(PaymentService::class)->startCheckout($booking);

    $this->actingAs($booking->student)
        ->post(route('student.payments.method', $payment), ['method' => PaymentMethod::OnlineTransfer->value]);

    $this->actingAs($booking->student)
        ->get(route('student.payments.checkout', $payment))
        ->assertOk()
        ->assertSee('Hatton National Bank')
        ->assertSee('9876543210')
        ->assertSee('Submit the slip');
});

it('shows the awaiting verification state on the checkout and the admin card', function () {
    Storage::fake('local');
    manualBankAccount();

    $payment = submitSlipFor(ownedBooking());

    $this->actingAs($payment->student)
        ->get(route('student.payments.checkout', $payment))
        ->assertOk()
        ->assertSee('Your slip is with our team')
        ->assertSee('Replace the slip');

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.payments.show', $payment))
        ->assertOk()
        ->assertSee('Manual payment proof')
        ->assertSee('Verify and confirm payment')
        ->assertSee('Reject the slip');
});

it('shows the rejection reason and a fresh upload form', function () {
    Storage::fake('local');
    manualBankAccount();

    $payment = submitSlipFor(ownedBooking());

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.payments.reject', $payment), ['reason' => 'Blurry slip.']);

    $this->actingAs($payment->student)
        ->get(route('student.payments.checkout', $payment))
        ->assertOk()
        ->assertSee('We could not verify your last slip.')
        ->assertSee('Blurry slip.')
        ->assertSee('Upload a new slip');
});
