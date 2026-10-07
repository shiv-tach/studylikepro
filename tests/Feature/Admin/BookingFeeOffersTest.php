<?php

use App\Enums\BookingFeeDiscountType;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\BookingFeePromotion;
use App\Models\User;
use Carbon\CarbonImmutable;

it('lists special offers next to the fee students pay today', function () {
    $admin = User::factory()->admin()->create();
    BookingFeePromotion::factory()->create(['name' => 'Free booking weekend']);
    BookingFeePromotion::factory()->inactive()->create(['name' => 'Paused offer']);

    $this->actingAs($admin)
        ->get(route('admin.offers.index'))
        ->assertOk()
        ->assertSee('Special offers')
        ->assertSee('Free booking weekend')
        ->assertSee('Paused offer')
        ->assertSee('RS: 100.00')
        ->assertSee('Students pay right now')
        ->assertSee('Running')
        ->assertSee('Paused');
});

it('creates a waiver offer that makes new bookings free of the fee', function () {
    $admin = User::factory()->admin()->create();
    $startsAt = CarbonImmutable::now('Asia/Colombo')->subDay()->startOfMinute();
    $endsAt = CarbonImmutable::now('Asia/Colombo')->addWeek()->startOfMinute();

    $this->actingAs($admin)
        ->post(route('admin.offers.store'), [
            'name' => 'Free booking weekend',
            'discount_type' => 'waive',
            'starts_at' => $startsAt->format('Y-m-d\TH:i'),
            'ends_at' => $endsAt->format('Y-m-d\TH:i'),
            'is_active' => '1',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $offer = BookingFeePromotion::query()->firstOrFail();

    expect($offer->name)->toBe('Free booking weekend')
        ->and($offer->discount_type)->toBe(BookingFeeDiscountType::Waive)
        ->and($offer->discount_value)->toBe(0)
        ->and($offer->is_active)->toBeTrue()
        // The window is typed in marketplace time and stored in UTC.
        ->and($offer->starts_at->timestamp)->toBe($startsAt->utc()->timestamp)
        ->and($offer->ends_at->timestamp)->toBe($endsAt->utc()->timestamp);

    // A student booking while the offer runs pays no booking fee.
    $booking = reserveFeeBooking(bookingFeeScenario());

    expect($booking->booking_fee_promotion_id)->toBe($offer->id)
        ->and($booking->netBookingFeeMinor())->toBe(0)
        ->and($booking->totalMinor())->toBe(60000);
});

it('creates percentage and fixed offers with the right stored value', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.offers.store'), [
            'name' => 'Quarter off the fee',
            'discount_type' => 'percent',
            'discount_percent' => 25,
            'is_active' => '1',
        ])
        ->assertSessionHasNoErrors();

    $this->actingAs($admin)
        ->post(route('admin.offers.store'), [
            'name' => 'Rs 40 off',
            'discount_type' => 'fixed',
            'discount_amount' => 40,
        ])
        ->assertSessionHasNoErrors();

    $offers = BookingFeePromotion::query()->orderBy('id')->get();

    expect($offers[0]->discount_type)->toBe(BookingFeeDiscountType::Percent)
        ->and($offers[0]->discount_value)->toBe(25)
        ->and($offers[0]->is_active)->toBeTrue()
        // Rupees are stored in minor units, like every other amount.
        ->and($offers[1]->discount_type)->toBe(BookingFeeDiscountType::Fixed)
        ->and($offers[1]->discount_value)->toBe(4000)
        ->and($offers[1]->is_active)->toBeFalse();

    // The edit page pre-fills each discount type correctly.
    $this->actingAs($admin)
        ->get(route('admin.offers.edit', $offers[0]))
        ->assertOk()
        ->assertSee('Quarter off the fee')
        ->assertSee('value="25"', false);

    $this->actingAs($admin)
        ->get(route('admin.offers.edit', $offers[1]))
        ->assertOk()
        ->assertSee('Rs 40 off')
        ->assertSee('value="40.00"', false);
});

it('validates the offer window and discount', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.offers.store'), [
            'name' => '',
            'discount_type' => 'percent',
            'discount_percent' => 150,
            'starts_at' => '2026-12-10T09:00',
            'ends_at' => '2026-12-08T09:00',
        ])
        ->assertSessionHasErrors(['name', 'discount_percent', 'ends_at']);

    $this->actingAs($admin)
        ->post(route('admin.offers.store'), [
            'name' => 'Missing the percentage',
            'discount_type' => 'percent',
        ])
        ->assertSessionHasErrors('discount_percent');

    $this->actingAs($admin)
        ->post(route('admin.offers.store'), [
            'name' => 'Not a discount type',
            'discount_type' => 'free-money',
        ])
        ->assertSessionHasErrors('discount_type');

    expect(BookingFeePromotion::query()->count())->toBe(0);
});

it('pauses and deletes an offer without touching bookings already priced', function () {
    $admin = User::factory()->admin()->create();
    $offer = BookingFeePromotion::factory()->create(['name' => 'Free booking weekend']);

    $booking = reserveFeeBooking(bookingFeeScenario());

    expect($booking->booking_fee_promotion_id)->toBe($offer->id);

    $this->actingAs($admin)
        ->put(route('admin.offers.update', $offer), [
            'name' => 'Free booking weekend',
            'discount_type' => 'waive',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.offers.edit', $offer));

    expect($offer->fresh()->is_active)->toBeFalse();

    $this->actingAs($admin)
        ->get(route('admin.offers.edit', $offer))
        ->assertOk()
        ->assertSee('Free booking weekend')
        ->assertDontSee('Running now');

    $this->actingAs($admin)
        ->delete(route('admin.offers.destroy', $offer))
        ->assertRedirect(route('admin.offers.index'));

    $booking->refresh();

    expect(BookingFeePromotion::query()->count())->toBe(0)
        ->and($booking->booking_fee_discount_minor)->toBe(10000)
        ->and($booking->netBookingFeeMinor())->toBe(0)
        ->and($booking->booking_fee_promotion_id)->toBeNull();
});

it('shows the special offers link in the admin navigation', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Special offers')
        ->assertSee(route('admin.offers.index'));
});

it('keeps the offers console away from students and teachers', function () {
    $offer = BookingFeePromotion::factory()->create();
    $outsiders = [
        User::factory()->student()->create(),
        User::factory()->teacher()->create(),
    ];

    foreach ($outsiders as $outsider) {
        $this->actingAs($outsider)->get(route('admin.offers.index'))->assertForbidden();
        $this->actingAs($outsider)->get(route('admin.offers.edit', $offer))->assertForbidden();
        $this->actingAs($outsider)
            ->post(route('admin.offers.store'), ['name' => 'Nope', 'discount_type' => 'waive'])
            ->assertForbidden();
        $this->actingAs($outsider)
            ->put(route('admin.offers.update', $offer), ['name' => 'Nope', 'discount_type' => 'waive'])
            ->assertForbidden();
        $this->actingAs($outsider)->delete(route('admin.offers.destroy', $offer))->assertForbidden();
    }

    expect($offer->fresh()->name)->toBe('Free booking weekend')
        ->and(Booking::query()->count())->toBe(0)
        ->and(ActivityLog::query()->count())->toBe(0);
});
