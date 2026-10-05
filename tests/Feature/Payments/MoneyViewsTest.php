<?php

use App\Enums\EarningStatus;
use App\Enums\PayoutStatus;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\BookingService;
use App\Services\Payments\PayoutService;
use App\Services\Payments\RefundService;
use App\Services\PlatformSettings;

it('renders a printable receipt with the amounts and any refunds', function () {
    $scenario = paidBookingScenario(priceMinor: 100000);

    $this->actingAs($scenario['student'])
        ->get(route('receipts.show', $scenario['booking']))
        ->assertOk()
        ->assertSee('Receipt')
        ->assertSee('1,000')
        ->assertSee($scenario['payment']->gateway_payment_id)
        ->assertSee('Free cancellation');

    app(RefundService::class)->refund(
        $scenario['payment'],
        50,
        Refund::INITIATED_BY_ADMIN,
        'Lesson cut short.',
    );

    $this->actingAs($scenario['student'])
        ->get(route('receipts.show', $scenario['booking']))
        ->assertOk()
        ->assertSee('Refund (50%)')
        ->assertSee('Lesson cut short.')
        ->assertSee('Net paid');
});

it('keeps the receipt between the payer and the admins', function () {
    $scenario = paidBookingScenario();
    $stranger = User::factory()->student()->onboarded()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($stranger)
        ->get(route('receipts.show', $scenario['booking']))
        ->assertStatus(403);

    $this->actingAs($admin)
        ->get(route('receipts.show', $scenario['booking']))
        ->assertOk();
});

it('has no receipt for a lesson that has not been paid for', function () {
    $booking = ownedBooking();

    $this->actingAs($booking->student)
        ->get(route('receipts.show', $booking))
        ->assertStatus(404);
});

it('shows the teacher what is available, pending and paid out', function () {
    $scenario = paidBookingScenario(priceMinor: 100000);
    $scenario['earning']->forceFill([
        'status' => EarningStatus::Eligible,
        'available_at' => now()->subMinute(),
    ])->save();

    $this->actingAs($scenario['teacherUser'])
        ->get(route('teacher.earnings.index'))
        ->assertOk()
        ->assertSee('Available for payout')
        ->assertSee('850')
        ->assertSee('Platform commission is 15%')
        ->assertSee('No payouts yet');
});

it('shows a reversed earning on the teacher ledger', function () {
    $scenario = paidBookingScenario(priceMinor: 100000);
    $scenario['earning']->forceFill(['status' => EarningStatus::Reversed, 'reversed_minor' => 85000])->save();

    $this->actingAs($scenario['teacherUser'])
        ->get(route('teacher.earnings.index'))
        ->assertOk()
        ->assertSee('Reversed')
        ->assertSee('850');
});

it('lists payments with money totals for the admin', function () {
    $scenario = paidBookingScenario(priceMinor: 100000);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.payments.index'))
        ->assertOk()
        ->assertSee('Collected')
        ->assertSee($scenario['student']->name)
        ->assertSee($scenario['payment']->gateway_payment_id)
        ->assertSee('1,000')
        ->assertSee('Platform commission')
        ->assertSee('150')
        ->assertSee('Payouts due')
        ->assertSee('Refund');
});

it('filters the payments list by status', function () {
    $paid = paidBookingScenario(priceMinor: 100000);
    $pending = Payment::factory()->pending()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.payments.index', ['status' => 'created']))
        ->assertOk()
        ->assertSee($pending->student->name)
        ->assertDontSee($paid['student']->name);
});

it('keeps the money console away from students and teachers', function () {
    $scenario = paidBookingScenario();

    $this->actingAs($scenario['student'])->get(route('admin.payments.index'))->assertStatus(403);
    $this->actingAs($scenario['teacherUser'])->get(route('admin.payments.index'))->assertStatus(403);
    $this->actingAs($scenario['student'])->post(route('admin.payments.refund', $scenario['payment']), ['percent' => 100])->assertStatus(403);
    $this->actingAs($scenario['teacherUser'])->get(route('admin.payouts.index'))->assertStatus(403);
    $this->actingAs($scenario['teacherUser'])->get(route('admin.settings.edit'))->assertStatus(403);
});

it('queues a payout batch for a teacher and marks it paid', function () {
    $scenario = paidBookingScenario(priceMinor: 100000);
    $scenario['earning']->forceFill(['status' => EarningStatus::Eligible])->save();

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.payouts.index'))
        ->assertOk()
        ->assertSee($scenario['teacherUser']->name)
        ->assertSee('850');

    $this->actingAs($admin)
        ->post(route('admin.payouts.store'), ['teacher_profile_id' => $scenario['teacher']->id])
        ->assertRedirect(route('admin.payouts.index'))
        ->assertSessionHas('status', 'payout-created');

    $payout = $scenario['teacher']->payouts()->firstOrFail();

    expect($payout->amount_minor)->toBe(85000)
        ->and($payout->lessons_count)->toBe(1)
        ->and($payout->status)->toBe(PayoutStatus::Pending)
        ->and($scenario['earning']->refresh()->payout_id)->toBe($payout->id)
        ->and($scenario['earning']->status)->toBe(EarningStatus::Paid);

    $this->actingAs($admin)
        ->post(route('admin.payouts.mark-paid', $payout), ['reference' => 'UTR123'])
        ->assertSessionHas('status', 'payout-paid');

    expect($payout->refresh()->status)->toBe(PayoutStatus::Paid)
        ->and($payout->paid_at)->not->toBeNull()
        ->and($payout->reference)->toBe('UTR123');

    // The ledger row is settled, so nothing is left to batch.
    expect(app(PayoutService::class)->createFor($scenario['teacher']))->toBeNull();
});

it('will not batch a reversed earning', function () {
    $scenario = paidBookingScenario();
    $scenario['earning']->forceFill([
        'status' => EarningStatus::Eligible,
        'reversed_minor' => $scenario['earning']->amount_minor,
    ])->save();

    expect(app(PayoutService::class)->createFor($scenario['teacher']))->toBeNull();
});

it('renders the settings form with the real setting keys', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.settings.edit'))
        ->assertOk()
        ->assertSee('name="commission_percent"', false)
        ->assertSee('name="currency"', false)
        ->assertSee('name="hold_ttl_minutes"', false)
        ->assertSee('name="student_cancel_window_hours"', false)
        ->assertSee('name="cancellation_policy_text"', false)
        ->assertDontSee('name="0"', false);
});

it('lets an admin retune commission and the refund policy', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.settings.edit'))
        ->assertOk()
        ->assertSee('Platform commission');

    $this->actingAs($admin)
        ->put(route('admin.settings.update'), [
            'commission_percent' => 20,
            'currency' => 'INR',
            'hold_ttl_minutes' => 45,
            'student_cancel_window_hours' => 12,
            'refund_student_percent' => 100,
            'refund_teacher_percent' => 100,
            'cancellation_policy_text' => 'Custom policy copy.',
        ])
        ->assertRedirect(route('admin.settings.edit'))
        ->assertSessionHas('status', 'settings-saved');

    $settings = app(PlatformSettings::class);

    expect($settings->int('commission_percent'))->toBe(20)
        ->and($settings->int('hold_ttl_minutes'))->toBe(45)
        ->and($settings->int('student_cancel_window_hours'))->toBe(12)
        ->and($settings->string('cancellation_policy_text'))->toBe('Custom policy copy.');

    // New bookings pick the new commission up.
    $booking = ownedBooking();
    expect($booking->fresh()->expires_at->diffInMinutes(now()))->toBeLessThanOrEqual(45);

    $breakdown = app(BookingService::class)->feeBreakdown(100000);
    expect($breakdown['platform_fee_minor'])->toBe(20000);
});

it('rejects settings that are not numbers where numbers are expected', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->put(route('admin.settings.update'), ['commission_percent' => 'lots'])
        ->assertSessionHasErrors('commission_percent');

    expect(app(PlatformSettings::class)->int('commission_percent'))->toBe(15);
});
