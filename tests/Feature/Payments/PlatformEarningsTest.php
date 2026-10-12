<?php

use App\Models\Payment;
use App\Models\Refund;
use App\Models\ServicePurchase;
use App\Models\User;
use App\Services\Payments\RefundService;

it('shows the platform earnings console to admins', function () {
    $scenario = paidBookingScenario(priceMinor: 100000);
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.earnings.index'))
        ->assertOk()
        ->assertSee('Platform earnings')
        ->assertSee('Net platform earnings')
        ->assertSee('Collections by source')
        ->assertSee('Monthly ledger')
        ->assertSee($scenario['student']->name)
        // Collected, commission and the teacher's payout all surface as formatted money.
        ->assertSee(platform_settings()->formatMinor(100000))
        ->assertSee(platform_settings()->formatMinor(15000))
        ->assertSee(platform_settings()->formatMinor(85000));
});

it('nets refunds and teacher payouts out of platform earnings', function () {
    $scenario = paidBookingScenario(priceMinor: 100000);
    $admin = User::factory()->admin()->create();

    app(RefundService::class)->refund(
        $scenario['payment'],
        100,
        Refund::INITIATED_BY_ADMIN,
        'Lesson cancelled by the teacher.',
    );

    $response = $this->actingAs($admin)->get(route('admin.earnings.index'))->assertOk();

    // A full refund returns everything collected and reverses the teacher payout,
    // so the platform keeps nothing from that collection.
    expect($response->viewData('totals'))->toMatchArray([
        'collected' => 100000,
        'refunded' => 100000,
        'teacher' => 0,
        'net' => 0,
    ]);
});

it('splits collections by the source they came from', function () {
    $scenario = paidBookingScenario(priceMinor: 100000);
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->get(route('admin.earnings.index'))->assertOk();

    expect($response->viewData('sources'))->toHaveCount(2)
        ->and($response->viewData('sources')[0])->toMatchArray([
            'label' => 'Lesson payments',
            'payments' => 1,
            'collected' => 100000,
            'commission' => 15000,
        ])
        ->and($response->viewData('sources')[1]['payments'])->toBe(0);
});

it('reports a service purchase collection without a booking', function () {
    $admin = User::factory()->admin()->create();
    $purchase = ServicePurchase::factory()->create();

    $payment = Payment::factory()->create([
        'booking_id' => null,
        'service_purchase_id' => $purchase->id,
        'student_id' => $purchase->student_id,
        'amount_minor' => $purchase->price_minor + $purchase->netBookingFeeMinor(),
        'currency' => $purchase->currency,
    ]);

    $response = $this->actingAs($admin)->get(route('admin.earnings.index'))->assertOk();

    expect($response->viewData('totals'))->toMatchArray([
        'payments' => 1,
        'collected' => $payment->amount_minor,
        'commission' => $purchase->platform_fee_minor,
        'teacher' => $purchase->teacher_payout_minor,
    ])->and($response->viewData('sources')[1]['payments'])->toBe(1);
});

it('honours the date range on the earnings console', function () {
    $scenario = paidBookingScenario(priceMinor: 100000);
    $scenario['payment']->forceFill(['captured_at' => now()->subDays(60)])->save();
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('admin.earnings.index'))
        ->assertOk()
        ->assertDontSee($scenario['student']->name);

    $this->actingAs($admin)
        ->get(route('admin.earnings.index', [
            'from' => now()->subDays(90)->toDateString(),
            'to' => now()->toDateString(),
        ]))
        ->assertOk()
        ->assertSee($scenario['student']->name);
});

it('keeps the earnings console away from students and teachers', function () {
    $scenario = paidBookingScenario();

    $this->actingAs($scenario['student'])->get(route('admin.earnings.index'))->assertStatus(403);
    $this->actingAs($scenario['teacherUser'])->get(route('admin.earnings.index'))->assertStatus(403);
});

it('exports the earnings ledger as CSV', function () {
    paidBookingScenario(priceMinor: 100000);
    $admin = User::factory()->admin()->create();

    $csv = $this->actingAs($admin)
        ->get(route('admin.reports.export', ['type' => 'earnings']))
        ->assertOk()
        ->streamedContent();

    expect($csv)->toContain('Net platform')
        ->toContain('lessons')
        ->toContain('1000.00');
});
