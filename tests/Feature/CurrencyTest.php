<?php

use App\Enums\EarningStatus;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Notifications\BookingConfirmed;
use App\Services\AdminCsvExporter;
use App\Services\Payments\PayoutService;
use App\Services\PlatformSettings;
use Illuminate\Support\Facades\Notification;

/**
 * The marketplace trades in Sri Lankan rupees. Amounts are held in minor units
 * and rendered in exactly one place — PlatformSettings::formatMinor — so every
 * page, email and export reads "RS: 1,250.00".
 */
it('renders amounts with the LKR symbol, a thousands separator and two decimals', function () {
    $settings = app(PlatformSettings::class);

    expect($settings->formatMinor(0))->toBe('RS: 0.00')
        ->and($settings->formatMinor(125000))->toBe('RS: 1,250.00')
        ->and($settings->formatMinor(52500))->toBe('RS: 525.00')
        ->and($settings->formatMinor(26250))->toBe('RS: 262.50')
        ->and($settings->formatMinor(123456789))->toBe('RS: 1,234,567.89');
});

it('falls back to the ISO code when the marketplace currency is changed', function () {
    $settings = app(PlatformSettings::class);

    $settings->set('currency', 'USD');

    expect($settings->currencySymbol())->toBe('USD')
        ->and($settings->formatMinor(125000))->toBe('USD 1,250.00');

    $settings->set('currency', 'LKR');

    expect($settings->currencySymbol())->toBe('RS:');
});

it('records new bookings in LKR', function () {
    $booking = Booking::factory()->create();

    expect(config('studylikepro.currency'))->toBe('LKR')
        ->and($booking->currency)->toBe('LKR')
        ->and(platform_settings()->get('currency'))->toBe('LKR');
});

it('shows LKR prices on the pages students and teachers use', function () {
    $scenario = paidBookingScenario(priceMinor: 72500);

    $this->actingAs($scenario['student'])
        ->get(route('student.bookings.show', $scenario['booking']))
        ->assertOk()
        ->assertSee('RS: 725.00');

    $this->actingAs($scenario['student'])
        ->get(route('receipts.show', $scenario['booking']))
        ->assertOk()
        ->assertSee('RS: 725.00');

    $this->actingAs($scenario['teacherUser'])
        ->get(route('teacher.earnings.index'))
        ->assertOk()
        ->assertSee('RS:');

    $this->get(route('teachers.show', $scenario['teacher']))
        ->assertOk()
        ->assertSee('RS:');
});

it('shows LKR prices in the admin console and its exports', function () {
    $admin = User::factory()->admin()->create();
    $scenario = paidBookingScenario(priceMinor: 100000);

    $this->actingAs($admin)
        ->get(route('admin.payments.show', $scenario['payment']))
        ->assertOk()
        ->assertSee('RS: 1,000.00');

    $this->actingAs($admin)
        ->get(route('admin.reports.index'))
        ->assertOk()
        ->assertSee('RS: 1,000.00');

    $response = $this->actingAs($admin)->get(route('admin.reports.export', ['type' => 'payments']));
    $response->assertOk();

    expect($response->streamedContent())->toContain('LKR');
});

it('writes the currency on payments, earnings and payouts', function () {
    $scenario = paidBookingScenario();

    $scenario['earning']->forceFill(['status' => EarningStatus::Eligible])->save();

    expect($scenario['payment']->currency)->toBe('LKR')
        ->and($scenario['earning']->refresh()->currency)->toBe('LKR');

    $payout = app(PayoutService::class)->createFor($scenario['teacher'], 'Cycle 1');

    expect($payout?->currency)->toBe('LKR');
});

it('mentions the currency in notification emails', function () {
    $scenario = paidBookingScenario(priceMinor: 80000);

    Notification::send(
        $scenario['student'],
        new BookingConfirmed($scenario['booking']),
    );

    $messages = app('mailer')->getSymfonyTransport()->messages();
    $html = $messages->map(fn ($message) => (string) $message->getOriginalMessage()->getHtmlBody())->implode("\n");

    expect($html)->toContain('RS: 800.00');
});

it('reads the symbol from config so a rebrand is one line', function () {
    config(['studylikepro.currency_symbol' => 'SLR#']);

    expect(app(PlatformSettings::class)->formatMinor(5000))->toBe('SLR# 50.00');
});

it('exports the currency column from every money table', function () {
    $admin = User::factory()->admin()->create();
    $scenario = paidBookingScenario();

    $scenario['earning']->forceFill(['status' => EarningStatus::Eligible])->save();
    app(PayoutService::class)->createFor($scenario['teacher'], 'Cycle 1');

    foreach (AdminCsvExporter::TYPES as $type) {
        $csv = $this->actingAs($admin)
            ->get(route('admin.reports.export', ['type' => $type]))
            ->streamedContent();

        expect($csv)->not->toContain('INR');
    }

    expect(Payment::query()->where('currency', 'INR')->count())->toBe(0);
});
