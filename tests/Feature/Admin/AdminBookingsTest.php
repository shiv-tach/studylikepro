<?php

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\User;

it('lists every booking with both parties and the money split', function () {
    $admin = User::factory()->admin()->create();

    $booking = Booking::factory()->create([
        'learner_name' => 'Ishaan Rao',
    ]);

    $this->actingAs($admin)
        ->get(route('admin.bookings.index'))
        ->assertOk()
        ->assertSee($booking->student->name)
        ->assertSee($booking->teacherProfile->user->name)
        ->assertSee('Ishaan Rao')
        ->assertSee('700')
        ->assertSee('Confirmed');
});

it('filters bookings by status', function () {
    $admin = User::factory()->admin()->create();

    $confirmed = Booking::factory()->create(['status' => BookingStatus::Confirmed]);
    $cancelled = Booking::factory()->create(['status' => BookingStatus::Cancelled]);

    $this->actingAs($admin)
        ->get(route('admin.bookings.index', ['status' => BookingStatus::Cancelled->value]))
        ->assertOk()
        ->assertSee($cancelled->student->name)
        ->assertDontSee($confirmed->student->name);
});

it('searches bookings by student name and email', function () {
    $admin = User::factory()->admin()->create();
    $booking = Booking::factory()->create();

    $this->actingAs($admin)
        ->get(route('admin.bookings.index', ['search' => $booking->student->email]))
        ->assertOk()
        ->assertSee($booking->student->name);

    $this->actingAs($admin)
        ->get(route('admin.bookings.index', ['search' => 'nobody-matches-this']))
        ->assertOk()
        ->assertSee('No bookings match these filters yet.');
});

it('offers admin cancellation only while a booking is still live', function () {
    $admin = User::factory()->admin()->create();

    $hold = Booking::factory()->hold()->create();
    $completed = Booking::factory()->create(['status' => BookingStatus::Completed]);

    $this->actingAs($admin)
        ->get(route('admin.bookings.show', $hold))
        ->assertOk()
        ->assertSee(route('admin.bookings.cancel', $hold));

    $this->actingAs($admin)
        ->get(route('admin.bookings.show', $completed))
        ->assertOk()
        ->assertDontSee(route('admin.bookings.cancel', $completed));

    expect($hold->refresh()->status)->toBe(BookingStatus::PendingPayment)
        ->and($completed->refresh()->status)->toBe(BookingStatus::Completed);
});

it('opens one booking with its parties, timeline, chat and money', function () {
    $admin = User::factory()->admin()->create();
    $booking = Booking::factory()->create(['status' => BookingStatus::Completed]);

    $this->actingAs($admin)
        ->get(route('admin.bookings.show', $booking))
        ->assertOk()
        ->assertSee($booking->student->name)
        ->assertSee($booking->teacherProfile->user->name)
        ->assertSee('Timeline')
        ->assertSee('Booked');
});

it('filters bookings by date range', function () {
    $admin = User::factory()->admin()->create();

    $thisMonth = Booking::factory()->create(['starts_at' => now()->addDays(3), 'ends_at' => now()->addDays(3)->addHour()]);
    $nextYear = Booking::factory()->create(['starts_at' => now()->addYear(), 'ends_at' => now()->addYear()->addHour()]);

    $this->actingAs($admin)
        ->get(route('admin.bookings.index', [
            'from' => now()->toDateString(),
            'to' => now()->addDays(10)->toDateString(),
        ]))
        ->assertOk()
        ->assertSee($thisMonth->student->name)
        ->assertDontSee($nextYear->student->name);
});

it('force completes a lesson support has confirmed was delivered', function () {
    $admin = User::factory()->admin()->create();
    $booking = Booking::factory()->create(['status' => BookingStatus::Confirmed]);

    $this->actingAs($admin)
        ->post(route('admin.bookings.force-complete', $booking))
        ->assertRedirect(route('admin.bookings.show', $booking));

    expect($booking->refresh()->status)->toBe(BookingStatus::Completed);
});
