<?php

use App\Models\Booking;
use App\Services\BookingTransitionService;

/**
 * The status timeline is built by Booking::timeline(). The student lesson page
 * renders it from the Vue island (see `bookingShowConfig()` in tests/Pest.php),
 * so these tests pin the steps it hands the view.
 */
it('closes the timeline with the rate and review step', function () {
    $scenario = classroomScenario();
    $booking = $scenario['booking'];

    // The final step is on the page straight away and waits for the lesson.
    $response = $this->actingAs($scenario['student'])
        ->get(route('student.bookings.show', $booking))
        ->assertOk()
        ->assertSee('The student has not rated this lesson yet.');

    $steps = collect(bookingShowConfig($response)['timeline']['steps'])->keyBy('key');

    expect($steps->keys()->all())->toBe(['requested', 'confirmed', 'in_progress', 'completed', 'reviewed'])
        ->and($steps['reviewed']['label'])->toBe('Rate & review')
        ->and($steps['reviewed']['at'])->toBeNull();

    $this->travelTo($booking->starts_at->addMinutes(10));
    app(BookingTransitionService::class)->complete($booking);

    $this->actingAs($scenario['student'])
        ->post(route('student.reviews.store', $booking->refresh()), ['rating' => 5, 'comment' => 'Superb.']);

    $this->actingAs($scenario['student'])
        ->get(route('student.bookings.show', $booking))
        ->assertOk()
        ->assertSee('The student rated this lesson 5/5.');

    $steps = collect($booking->refresh()->timeline());

    expect($steps->pluck('key')->all())->toBe(['requested', 'confirmed', 'in_progress', 'completed', 'reviewed'])
        ->and($steps->firstWhere('key', 'reviewed')['at']->timestamp)->toBe($booking->review->created_at->timestamp);
});

it('stamps the cancellation step when a lesson is called off', function () {
    $scenario = paidBookingScenario();

    app(BookingTransitionService::class)->cancel($scenario['booking'], Booking::CANCELLED_BY_TEACHER, 'Teacher is unwell.');

    $steps = collect($scenario['booking']->refresh()->timeline());
    $cancelled = $steps->firstWhere('key', 'cancelled');

    expect($steps->pluck('key')->all())->toBe(['requested', 'cancelled'])
        ->and($cancelled['description'])->toBe('Teacher cancelled this lesson.')
        ->and($cancelled['at'])->not->toBeNull();
});

it('leaves the confirmation step pending while the hold is unpaid', function () {
    $booking = Booking::factory()->hold()->create();

    $steps = collect($booking->timeline());

    expect($steps->pluck('key')->all())->toBe(['requested', 'confirmed', 'in_progress', 'completed', 'reviewed'])
        ->and($steps->firstWhere('key', 'confirmed')['at'])->toBeNull()
        ->and($steps->firstWhere('key', 'reviewed')['at'])->toBeNull();
});
