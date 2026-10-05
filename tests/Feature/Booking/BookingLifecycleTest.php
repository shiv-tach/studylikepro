<?php

use App\Enums\BookingStatus;
use App\Enums\RequestStatus;
use App\Events\BookingConfirmed;
use App\Events\BookingExpired;
use App\Models\Booking;
use App\Models\TutoringRequest;
use App\Services\BookingTransitionService;
use Illuminate\Support\Facades\Event;
use Illuminate\Validation\ValidationException;

function transitions(): BookingTransitionService
{
    return app(BookingTransitionService::class);
}

it('confirms a hold, stamps the confirmation and clears the payment window', function () {
    Event::fake([BookingConfirmed::class]);

    $booking = Booking::factory()->hold()->create();

    transitions()->confirm($booking);

    $booking->refresh();

    expect($booking->status)->toBe(BookingStatus::Confirmed)
        ->and($booking->confirmed_at)->not->toBeNull()
        ->and($booking->expires_at)->toBeNull();

    Event::assertDispatched(BookingConfirmed::class, fn (BookingConfirmed $event) => $event->booking->is($booking));
});

it('treats a repeated confirmation as a no-op so gateway retries stay safe', function () {
    Event::fake([BookingConfirmed::class]);

    $booking = Booking::factory()->hold()->create();

    transitions()->confirm($booking);
    transitions()->confirm($booking->refresh());

    expect($booking->refresh()->status)->toBe(BookingStatus::Confirmed);

    Event::assertDispatchedTimes(BookingConfirmed::class, 1);
});

it('expires a lapsed hold instead of confirming it', function () {
    Event::fake([BookingConfirmed::class, BookingExpired::class]);

    $booking = Booking::factory()->hold()->create(['expires_at' => now()->subMinute()]);

    expect(fn () => transitions()->confirm($booking))
        ->toThrow(ValidationException::class);

    expect($booking->refresh()->status)->toBe(BookingStatus::Expired);

    Event::assertNotDispatched(BookingConfirmed::class);
    Event::assertDispatched(BookingExpired::class);
});

it('refuses to confirm a booking that is already cancelled', function () {
    $booking = Booking::factory()->create(['status' => BookingStatus::Cancelled]);

    expect(fn () => transitions()->confirm($booking))
        ->toThrow(ValidationException::class);
});

it('starts a lesson within the early-start window', function () {
    $booking = Booking::factory()->create([
        'status' => BookingStatus::Confirmed,
        'starts_at' => now()->addMinutes(10),
        'ends_at' => now()->addMinutes(70),
    ]);

    transitions()->start($booking);

    expect($booking->refresh()->status)->toBe(BookingStatus::InProgress)
        ->and($booking->started_at)->not->toBeNull();
});

it('refuses to start a lesson more than the early window before it begins', function () {
    $booking = Booking::factory()->create([
        'status' => BookingStatus::Confirmed,
        'starts_at' => now()->addHours(4),
        'ends_at' => now()->addHours(5),
    ]);

    expect(fn () => transitions()->start($booking))->toThrow(ValidationException::class);

    expect($booking->refresh()->status)->toBe(BookingStatus::Confirmed);
});

it('refuses to start a hold that has not been paid for', function () {
    $booking = Booking::factory()->hold()->create([
        'starts_at' => now()->addMinutes(5),
        'ends_at' => now()->addMinutes(65),
    ]);

    expect(fn () => transitions()->start($booking))->toThrow(ValidationException::class);
});

it('completes a lesson and closes the request it answered', function () {
    $request = TutoringRequest::factory()->create(['status' => RequestStatus::Matched]);

    $booking = Booking::factory()->create([
        'tutoring_request_id' => $request->id,
        'status' => BookingStatus::InProgress,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->subMinutes(5),
    ]);

    transitions()->complete($booking);

    expect($booking->refresh()->status)->toBe(BookingStatus::Completed)
        ->and($booking->completed_at)->not->toBeNull()
        ->and($request->refresh()->status)->toBe(RequestStatus::Closed);
});

it('refuses to complete a lesson before it starts', function () {
    $booking = Booking::factory()->create([
        'status' => BookingStatus::Confirmed,
        'starts_at' => now()->addDay(),
        'ends_at' => now()->addDay()->addHour(),
    ]);

    expect(fn () => transitions()->complete($booking))->toThrow(ValidationException::class);

    expect($booking->refresh()->status)->toBe(BookingStatus::Confirmed);
});

it('treats cancelled, expired and no-show bookings as terminal', function () {
    foreach ([BookingStatus::Cancelled, BookingStatus::Expired, BookingStatus::NoShow] as $status) {
        $booking = Booking::factory()->create(['status' => $status]);

        expect(transitions()->transitionsFrom($status))->toBeEmpty()
            ->and(fn () => transitions()->confirm($booking))->toThrow(ValidationException::class);
    }

    // A completed lesson can only move into a dispute, never back to life.
    expect(transitions()->transitionsFrom(BookingStatus::Completed))->toEqual([BookingStatus::Disputed]);
});

it('marks a no-show only once the lesson time has passed', function () {
    $early = Booking::factory()->create([
        'status' => BookingStatus::Confirmed,
        'starts_at' => now()->addHours(2),
        'ends_at' => now()->addHours(3),
    ]);

    expect(fn () => transitions()->markNoShow($early))->toThrow(ValidationException::class);

    $late = Booking::factory()->create([
        'status' => BookingStatus::Confirmed,
        'starts_at' => now()->subHour(),
        'ends_at' => now()->subMinutes(10),
    ]);

    transitions()->markNoShow($late);

    expect($late->refresh()->status)->toBe(BookingStatus::NoShow);
});

it('publishes the legal transitions for every status', function () {
    expect(transitions()->transitionsFrom(BookingStatus::PendingPayment))
        ->toEqualCanonicalizing([BookingStatus::Confirmed, BookingStatus::Cancelled, BookingStatus::Expired])
        ->and(transitions()->canTransition(BookingStatus::PendingPayment, BookingStatus::Completed))->toBeFalse()
        ->and(transitions()->canTransition(BookingStatus::Confirmed, BookingStatus::InProgress))->toBeTrue()
        ->and(transitions()->canTransition(BookingStatus::Completed, BookingStatus::Cancelled))->toBeFalse()
        ->and(transitions()->canTransition(BookingStatus::Confirmed, BookingStatus::NoShow))->toBeTrue();
});
