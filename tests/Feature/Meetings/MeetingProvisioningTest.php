<?php

use App\Enums\MeetingStatus;
use App\Exceptions\MeetingProvisioningException;
use App\Jobs\CreateBookingMeeting;
use App\Models\User;
use App\Services\BookingTransitionService;
use App\Services\Meetings\FakeMeetingProvider;
use App\Services\Meetings\MeetingService;

it('opens a classroom as soon as a hold is confirmed', function () {
    $booking = ownedBooking();

    expect($booking->meeting_status)->toBeNull();

    app(BookingTransitionService::class)->confirm($booking);
    $booking->refresh();

    expect($booking->meeting_status)->toBe(MeetingStatus::Ready)
        ->and($booking->meeting_provider)->toBe('fake')
        ->and($booking->meeting_external_id)->toStartWith("slp-{$booking->id}-")
        ->and($booking->meeting_url)->toContain('t=guest-')
        ->and($booking->host_meeting_url)->toContain('t=host-')
        ->and(FakeMeetingProvider::rooms())->toHaveCount(1);
});

it('keeps the existing room when provisioning runs again', function () {
    $booking = classroomScenario()['booking'];
    $original = $booking->meeting_external_id;

    CreateBookingMeeting::dispatch($booking->id);
    app(MeetingService::class)->provision($booking);

    expect(FakeMeetingProvider::rooms())->toHaveCount(1)
        ->and($booking->fresh()->meeting_external_id)->toBe($original);
});

it('records the failure and gives up after the configured attempts', function () {
    config(['studylikepro.meeting.provision_attempts' => 3]);
    FakeMeetingProvider::fail();

    $booking = ownedBooking();

    app(BookingTransitionService::class)->confirm($booking);
    $booking->refresh();

    expect($booking->meeting_status)->toBe(MeetingStatus::Failed)
        ->and($booking->meeting_error)->toContain('demo meeting provider')
        ->and($booking->meeting_url)->toBeNull()
        ->and(FakeMeetingProvider::attempts())->toBe(3);
});

it('lets the teacher regenerate a classroom that failed to open', function () {
    $scenario = classroomScenario(withRoom: false);

    app(MeetingService::class)->recordFailure($scenario['booking'], new MeetingProvisioningException('boom'));

    $this->actingAs($scenario['teacherUser'])
        ->post(route('classroom.retry', $scenario['booking']))
        ->assertRedirect()
        ->assertSessionHas('status', 'classroom-regenerated');

    $booking = $scenario['booking']->fresh();

    expect($booking->meeting_status)->toBe(MeetingStatus::Ready)
        ->and($booking->meeting_error)->toBeNull()
        ->and($booking->meetingIsReady())->toBeTrue();
});

it('lets an admin regenerate a failed classroom', function () {
    $scenario = classroomScenario(withRoom: false);

    app(MeetingService::class)->recordFailure($scenario['booking'], new MeetingProvisioningException('boom'));

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('classroom.retry', $scenario['booking']))
        ->assertRedirect();

    expect($scenario['booking']->fresh()->meeting_status)->toBe(MeetingStatus::Ready);
});

it('shows the failed classroom to admins on the bookings console', function () {
    $scenario = classroomScenario(withRoom: false);

    app(MeetingService::class)->recordFailure($scenario['booking'], new MeetingProvisioningException('room quota reached'));

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.bookings.index'))
        ->assertOk()
        ->assertSee('Classroom: Failed')
        ->assertSee('Regenerate link');
});

it('does not let a student regenerate the classroom', function () {
    $scenario = classroomScenario();

    $this->actingAs($scenario['student'])
        ->post(route('classroom.retry', $scenario['booking']))
        ->assertForbidden();
});

it('closes the room when the lesson is delivered', function () {
    $scenario = classroomScenario();
    $booking = $scenario['booking']->fresh();

    $this->travelTo($booking->starts_at->addMinutes(5));

    app(BookingTransitionService::class)->complete($booking);
    $booking->refresh();

    expect($booking->meeting_ended_at)->not->toBeNull()
        ->and(FakeMeetingProvider::endedRooms())->toContain($booking->meeting_external_id);
});

it('closes the room when a lesson is cancelled', function () {
    $scenario = classroomScenario();
    $booking = $scenario['booking']->fresh();

    app(BookingTransitionService::class)->cancel($booking, 'teacher', 'Feeling unwell.');

    expect($booking->fresh()->meeting_ended_at)->not->toBeNull()
        ->and(FakeMeetingProvider::endedRooms())->toHaveCount(1);
});
