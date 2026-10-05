<?php

use App\Enums\BookingStatus;
use App\Enums\RequestStatus;
use App\Enums\ResponseStatus;
use App\Models\Booking;
use App\Models\RequestResponse;
use App\Models\TeacherAvailabilitySlot;
use App\Models\TeacherProfile;
use App\Models\TutoringRequest;
use App\Services\RequestMatcher;
use Carbon\CarbonImmutable;

it('expires overdue requests and their pending proposals', function () {
    $request = TutoringRequest::factory()->create(['expires_at' => now()->subMinute()]);
    $proposal = RequestResponse::factory()->create(['tutoring_request_id' => $request->id]);

    $this->artisan('studylikepro:expire-requests')->assertSuccessful();

    expect($request->fresh()->status)->toBe(RequestStatus::Expired)
        ->and($proposal->fresh()->status)->toBe(ResponseStatus::Expired);
});

it('keeps requests that still have time open', function () {
    $request = TutoringRequest::factory()->create(['expires_at' => now()->addDay()]);

    $this->artisan('studylikepro:expire-requests')->assertSuccessful();

    expect($request->fresh()->status)->toBe(RequestStatus::Open);
});

it('releases an unpaid hold and frees the slot for other students', function () {
    $teacher = TeacherProfile::factory()->approved()->create(['timezone' => 'UTC']);
    $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(18, 0);
    $endsAt = $startsAt->addHour();

    TeacherAvailabilitySlot::factory()
        ->on($startsAt->dayOfWeek, '18:00', '20:00')
        ->create(['teacher_profile_id' => $teacher->id]);

    $booking = Booking::factory()->hold()->create([
        'teacher_profile_id' => $teacher->id,
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
        'expires_at' => now()->subMinute(),
    ]);

    $matcher = app(RequestMatcher::class);

    expect($booking->fresh()->status)->toBe(BookingStatus::PendingPayment)
        ->and($matcher->isSlotOpen($teacher, $startsAt, $endsAt))->toBeFalse();

    $this->artisan('studylikepro:expire-holds')->assertSuccessful();

    expect($booking->fresh()->status)->toBe(BookingStatus::Expired)
        ->and($matcher->isSlotOpen($teacher, $startsAt, $endsAt))->toBeTrue();
});

it('keeps live holds blocking the slot', function () {
    $teacher = TeacherProfile::factory()->approved()->create(['timezone' => 'UTC']);
    $startsAt = CarbonImmutable::now('UTC')->addDay()->setTime(18, 0);
    $endsAt = $startsAt->addHour();

    TeacherAvailabilitySlot::factory()
        ->on($startsAt->dayOfWeek, '18:00', '20:00')
        ->create(['teacher_profile_id' => $teacher->id]);

    $booking = Booking::factory()->hold()->create([
        'teacher_profile_id' => $teacher->id,
        'starts_at' => $startsAt,
        'ends_at' => $endsAt,
        'expires_at' => now()->addMinutes(10),
    ]);

    $this->artisan('studylikepro:expire-holds')->assertSuccessful();

    expect($booking->fresh()->status)->toBe(BookingStatus::PendingPayment)
        ->and(app(RequestMatcher::class)->isSlotOpen($teacher, $startsAt, $endsAt))->toBeFalse();
});
