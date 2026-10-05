<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Services\AdminCsvExporter;
use App\Services\Payments\RefundService;

it('shows revenue, commission and lesson totals for the range', function () {
    $admin = User::factory()->admin()->create();
    $scenario = paidBookingScenario(priceMinor: 100000);
    $scenario['booking']->update([
        'status' => BookingStatus::Completed,
        'completed_at' => now(),
        'starts_at' => now()->subDay(),
        'ends_at' => now()->subDay()->addHour(),
    ]);

    $response = $this->actingAs($admin)
        ->get(route('admin.reports.index'))
        ->assertOk()
        ->assertSee('Collected')
        ->assertSee('Commission')
        ->assertSee('Teacher performance');

    $commission = platform_settings()->formatMinor($scenario['booking']->platform_fee_minor);

    $response->assertSee($commission);
    $response->assertSee($scenario['teacherUser']->name);
});

it('excludes payments outside the selected range', function () {
    $admin = User::factory()->admin()->create();

    $outside = paidBookingScenario();
    $outside['payment']->forceFill(['captured_at' => now()->subMonths(3)])->save();

    $this->actingAs($admin)
        ->get(route('admin.reports.index', [
            'from' => now()->subDays(6)->toDateString(),
            'to' => now()->toDateString(),
        ]))
        ->assertOk()
        ->assertSee('No completed lessons in this range.')
        ->assertDontSee($outside['teacherUser']->name);
});

it('counts refunds against net revenue', function () {
    $admin = User::factory()->admin()->create();
    $scenario = paidBookingScenario(priceMinor: 50000);

    app(RefundService::class)->refund(
        $scenario['payment'],
        50,
        Refund::INITIATED_BY_ADMIN,
        'Goodwill',
    );

    $expectedNet = $scenario['payment']->amount_minor - (int) round($scenario['payment']->amount_minor / 2);

    $this->actingAs($admin)
        ->get(route('admin.reports.index'))
        ->assertOk()
        // Collections keep the full captured amount; refunds are subtracted from it.
        ->assertSee(platform_settings()->formatMinor($scenario['payment']->amount_minor))
        ->assertSee(platform_settings()->formatMinor((int) round($scenario['payment']->amount_minor / 2)))
        ->assertSee(platform_settings()->formatMinor($expectedNet));
});

it('downloads each report export as CSV', function () {
    $admin = User::factory()->admin()->create();

    foreach (AdminCsvExporter::TYPES as $type) {
        $response = $this->actingAs($admin)->get(route('admin.reports.export', ['type' => $type]));

        $response->assertOk();
        expect($response->streamedContent() !== '')->toBeTrue();
    }
});

it('rejects an unknown export type', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.reports.export', ['type' => 'secrets']))
        ->assertNotFound();
});

it('exports the payment list with its rows', function () {
    $admin = User::factory()->admin()->create();
    $scenario = paidBookingScenario();

    $response = $this->actingAs($admin)->get(route('admin.payments.export'));

    $response->assertOk();

    $csv = $response->streamedContent();

    expect($csv)->toContain('Order id')
        ->and($csv)->toContain($scenario['payment']->gateway_order_id)
        ->and($csv)->toContain($scenario['student']->name);
});

it('filters the payment list by status, gateway and date', function () {
    $admin = User::factory()->admin()->create();
    $scenario = paidBookingScenario();
    $failed = Payment::factory()->failed()->create(['booking_id' => $scenario['booking']->id]);

    $this->actingAs($admin)
        ->get(route('admin.payments.index', ['status' => 'captured']))
        ->assertOk()
        ->assertSee($scenario['payment']->gateway_payment_id ?? $scenario['payment']->gateway_order_id)
        ->assertDontSee($failed->gateway_order_id);

    $this->actingAs($admin)
        ->get(route('admin.payments.index', [
            'from' => now()->addDay()->toDateString(),
            'to' => now()->addDays(2)->toDateString(),
        ]))
        ->assertOk()
        ->assertSee('No payments match these filters yet.');
});

it('shows one payment with its refund history and refund control', function () {
    $admin = User::factory()->admin()->create();
    $scenario = paidBookingScenario();
    $scenario['payment']->forceFill(['gateway_payload' => ['status' => 'captured']])->save();

    $this->actingAs($admin)
        ->get(route('admin.payments.show', $scenario['payment']))
        ->assertOk()
        ->assertSee($scenario['student']->name)
        ->assertSee('Refund history')
        ->assertSee(route('admin.payments.refund', $scenario['payment']))
        ->assertSee('Gateway payload');
});

it('issues a refund from the payment detail and records it', function () {
    $admin = User::factory()->admin()->create();
    $scenario = paidBookingScenario();

    $this->actingAs($admin)
        ->post(route('admin.payments.refund', $scenario['payment']), [
            'percent' => 25,
            'reason' => 'Late cancellation by the teacher.',
        ])
        ->assertRedirect(route('admin.payments.show', $scenario['payment']));

    $refund = Refund::query()->firstOrFail();

    expect($refund->percent)->toBe(25)
        ->and($refund->initiated_by)->toBe(Refund::INITIATED_BY_ADMIN)
        ->and($refund->reason)->toBe('Late cancellation by the teacher.');

    expect(ActivityLog::query()->first()->description)->toContain('Refunded');

    $this->actingAs($admin)
        ->get(route('admin.payments.show', $scenario['payment']))
        ->assertOk()
        ->assertSee('Late cancellation by the teacher.');
});

it('will not refund an unpaid booking', function () {
    $admin = User::factory()->admin()->create();
    $booking = Booking::factory()->hold()->create();
    $payment = Payment::factory()->create([
        'booking_id' => $booking->id,
        'student_id' => $booking->student_id,
        'status' => PaymentStatus::Created,
    ]);

    $this->actingAs($admin)
        ->post(route('admin.payments.refund', $payment), ['percent' => 100])
        ->assertRedirect(route('admin.payments.show', $payment))
        ->assertSessionHas('status', 'refund-skipped');

    expect(Refund::query()->count())->toBe(0);
});
