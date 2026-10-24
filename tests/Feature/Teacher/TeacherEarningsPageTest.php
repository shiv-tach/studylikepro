<?php

use App\Enums\EarningStatus;
use App\Enums\PayoutStatus;
use App\Models\Payout;
use App\Models\ServicePurchase;
use App\Models\TeacherEarning;

it('renders the earnings dashboard island', function () {
    $scenario = paidBookingScenario(priceMinor: 100000);
    $scenario['earning']->forceFill([
        'status' => EarningStatus::Eligible,
        'available_at' => now()->subMinute(),
    ])->save();

    $response = $this->actingAs($scenario['teacherUser'])
        ->get(route('teacher.earnings.index'))
        ->assertOk();

    $config = teacherEarningsConfig($response);

    expect($config['stats']['tiles'])->toHaveCount(4)
        ->and($config['stats']['tiles'][0]['label'])->toBe('Available for payout')
        ->and($config['stats']['tiles'][0]['minor'])->toBe(85000)
        ->and($config['stats']['tiles'][0]['valueLabel'])->toBe('RS: 850.00')
        ->and($config['stats']['tiles'][2]['minor'])->toBe(0)
        ->and($config['stats']['reversed']['minor'])->toBe(0)
        ->and($config['ledger'])->toHaveCount(1)
        ->and($config['ledger'][0]['statusLabel'])->toBe('Available')
        ->and($config['ledger'][0]['statusTone'])->toBe('emerald')
        ->and($config['ledger'][0]['amountLabel'])->toBe('RS: 850.00')
        ->and($config['ledger'][0]['reversedLabel'])->toBeNull()
        // Every lesson row links to the lesson it came from.
        ->and($config['ledger'][0]['url'])->toBe(route('teacher.bookings.show', $scenario['booking']))
        ->and($config['pagination']['total'])->toBe(1)
        ->and($config['payouts'])->toBe([])
        ->and($config['trend']['months'])->toHaveCount(6)
        ->and($config['urls']['schedule'])->toBe(route('teacher.schedule.index'));
});

it('counts every status and filters the ledger without moving the totals', function () {
    $scenario = paidBookingScenario(priceMinor: 100000);
    $teacher = $scenario['teacher'];

    TeacherEarning::factory()->forTeacher($teacher)->count(2)->create();
    TeacherEarning::factory()->forTeacher($teacher)->paid()->create();

    $all = teacherEarningsConfig(
        $this->actingAs($scenario['teacherUser'])->get(route('teacher.earnings.index'))->assertOk()
    );

    // All + the four EarningStatus cases, with counts over the whole ledger.
    expect($all['statusTabs'])->toHaveCount(5)
        ->and($all['statusTabs'][0]['count'])->toBe(4)
        ->and($all['statusTabs'][1]['key'])->toBe(EarningStatus::Pending->value)
        ->and($all['statusTabs'][1]['count'])->toBe(3)
        ->and($all['statusTabs'][3]['key'])->toBe(EarningStatus::Paid->value)
        ->and($all['statusTabs'][3]['count'])->toBe(1)
        ->and($all['pagination']['total'])->toBe(4);

    $filtered = teacherEarningsConfig(
        $this->actingAs($scenario['teacherUser'])
            ->get(route('teacher.earnings.index', ['status' => EarningStatus::Paid->value]))
            ->assertOk()
    );

    expect($filtered['ledger'])->toHaveCount(1)
        ->and($filtered['ledger'][0]['statusLabel'])->toBe('Paid out')
        ->and($filtered['pagination']['filtered'])->toBeTrue()
        ->and($filtered['pagination']['total'])->toBe(1)
        // The tiles answer "what is my money", never "what is on this page".
        ->and($filtered['stats']['tiles'])->toBe($all['stats']['tiles']);
});

it('keeps the status filter on the ledger pagination links', function () {
    $scenario = paidBookingScenario();

    TeacherEarning::factory()->forTeacher($scenario['teacher'])->count(20)->create();

    $this->actingAs($scenario['teacherUser'])
        ->get(route('teacher.earnings.index', ['status' => EarningStatus::Pending->value]))
        ->assertOk()
        ->assertSee('status=pending&amp;page=2', false);
});

it('rejects an unknown status filter', function () {
    $scenario = paidBookingScenario();

    $this->actingAs($scenario['teacherUser'])
        ->get(route('teacher.earnings.index', ['status' => 'nope']))
        ->assertSessionHasErrors('status');
});

it('shows a reversed earning with the refund note', function () {
    $scenario = paidBookingScenario(priceMinor: 100000);
    $scenario['earning']->forceFill([
        'status' => EarningStatus::Reversed,
        'reversed_minor' => 85000,
    ])->save();

    $response = $this->actingAs($scenario['teacherUser'])
        ->get(route('teacher.earnings.index'))
        ->assertOk();

    $config = teacherEarningsConfig($response);

    expect($config['ledger'][0]['statusLabel'])->toBe('Reversed')
        ->and($config['ledger'][0]['statusTone'])->toBe('rose')
        ->and($config['ledger'][0]['reversedMinor'])->toBe(85000)
        ->and($config['ledger'][0]['reversedLabel'])->toBe('−RS: 850.00')
        ->and($config['stats']['reversed']['minor'])->toBe(85000)
        ->and($config['stats']['reversed']['label'])->toContain('RS: 850.00');

    $response->assertSee('Reversed by refunds');
});

it('shows a service purchase earning on the ledger', function () {
    $scenario = paidBookingScenario();
    $scenario['earning']->delete();

    $purchase = ServicePurchase::factory()->create([
        'teacher_profile_id' => $scenario['teacher']->id,
    ]);

    $earning = TeacherEarning::factory()
        ->forTeacher($scenario['teacher'])
        ->create([
            'booking_id' => null,
            'service_purchase_id' => $purchase->id,
            'amount_minor' => $purchase->teacher_payout_minor,
        ]);

    $config = teacherEarningsConfig(
        $this->actingAs($scenario['teacherUser'])->get(route('teacher.earnings.index'))->assertOk()
    );

    expect($config['ledger'])->toHaveCount(1)
        ->and($config['ledger'][0]['id'])->toBe($earning->id)
        ->and($config['ledger'][0]['title'])->toBe($purchase->fresh()->scopeLabel())
        ->and($config['ledger'][0]['typeLabel'])->toBe($purchase->type()->label())
        ->and($config['ledger'][0]['subtitle'])->toBe($purchase->student->name)
        ->and($config['ledger'][0]['url'])->toBe(route('teacher.orders.show', $purchase));
});

it('lists a payout batch beside the earning it paid', function () {
    $scenario = paidBookingScenario(priceMinor: 100000);

    $payout = Payout::factory()->create([
        'teacher_profile_id' => $scenario['teacher']->id,
        'amount_minor' => 85000,
    ])->forceFill([
        'status' => PayoutStatus::Paid,
        'paid_at' => now(),
    ]);
    $payout->save();

    $scenario['earning']->forceFill([
        'status' => EarningStatus::Paid,
        'payout_id' => $payout->id,
    ])->save();

    $config = teacherEarningsConfig(
        $this->actingAs($scenario['teacherUser'])->get(route('teacher.earnings.index'))->assertOk()
    );

    expect($config['payouts'])->toHaveCount(1)
        ->and($config['payouts'][0]['reference'])->toBe($payout->reference)
        ->and($config['payouts'][0]['lessonsLabel'])->toBe('1 lesson')
        ->and($config['payouts'][0]['statusLabel'])->toBe('Paid')
        ->and($config['payouts'][0]['statusTone'])->toBe('emerald')
        ->and($config['payouts'][0]['amountLabel'])->toBe('RS: 850.00')
        // The ledger row is traceable back to the batch that paid it.
        ->and($config['ledger'][0]['payoutReference'])->toBe($payout->reference)
        ->and($config['stats']['tiles'][2]['minor'])->toBe(85000);
});

it('shows the empty ledger and empty payouts for a teacher with no earnings', function () {
    $scenario = paidBookingScenario();
    $scenario['earning']->delete();

    $response = $this->actingAs($scenario['teacherUser'])
        ->get(route('teacher.earnings.index'))
        ->assertOk();

    $config = teacherEarningsConfig($response);

    expect($config['ledger'])->toBe([])
        ->and($config['pagination']['total'])->toBe(0)
        ->and($config['payouts'])->toBe([])
        ->and($config['trend']['max'])->toBe(0)
        ->and($config['trend']['months'])->toHaveCount(6)
        ->and($config['stats']['tiles'][0]['minor'])->toBe(0);

    $response->assertSee('No payouts yet');
});

it('groups the last six months and includes the empty ones', function () {
    $scenario = paidBookingScenario(priceMinor: 100000);

    $months = teacherEarningsConfig(
        $this->actingAs($scenario['teacherUser'])->get(route('teacher.earnings.index'))->assertOk()
    )['trend']['months'];

    expect($months)->toHaveCount(6)
        // Newest last: this month holds the one earning, the rest are zero.
        ->and($months[5]['minor'])->toBe(85000)
        ->and($months[5]['percent'])->toBe(100)
        ->and($months[5]['count'])->toBe(1)
        ->and($months[0]['minor'])->toBe(0)
        ->and($months[0]['count'])->toBe(0);
});

it('renders the same money without javascript', function () {
    $scenario = paidBookingScenario(priceMinor: 100000);
    $scenario['earning']->forceFill([
        'status' => EarningStatus::Eligible,
        'available_at' => now()->subMinute(),
    ])->save();

    $response = $this->actingAs($scenario['teacherUser'])
        ->get(route('teacher.earnings.index'))
        ->assertOk();

    // The no-JS twin prints the same formatted row, not the raw models.
    $config = teacherEarningsConfig($response);

    $response->assertSee('Available for payout')
        ->assertSee('RS: 850.00')
        ->assertSee($config['ledger'][0]['title'])
        ->assertSee('No payouts yet');
});
