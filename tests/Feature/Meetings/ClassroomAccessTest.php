<?php

use App\Enums\MeetingStatus;
use App\Exceptions\MeetingProvisioningException;
use App\Models\User;
use App\Services\BookingTransitionService;
use App\Services\Meetings\MeetingService;

it('shows the join button to the student inside the window and records the first join', function () {
    $scenario = classroomScenario();
    $booking = $scenario['booking'];

    $this->actingAs($scenario['student'])
        ->get(route('classroom.show', $booking))
        ->assertOk()
        ->assertSee('The classroom is open')
        ->assertSee($booking->meeting_url, false)
        ->assertDontSee($booking->host_meeting_url, false);

    expect($booking->fresh()->meeting_started_at)->not->toBeNull();
});

it('gives the teacher the host link instead of the participant link', function () {
    $scenario = classroomScenario();
    $booking = $scenario['booking'];

    $this->actingAs($scenario['teacherUser'])
        ->get(route('classroom.show', $booking))
        ->assertOk()
        ->assertSee('The classroom is open')
        ->assertSee($booking->host_meeting_url, false)
        ->assertDontSee($booking->meeting_url, false);
});

it('counts down instead of exposing the link before the window opens', function () {
    $scenario = classroomScenario(minutesFromNow: 60);
    $booking = $scenario['booking'];

    $this->actingAs($scenario['student'])
        ->get(route('classroom.show', $booking))
        ->assertOk()
        ->assertSee('Your classroom opens soon')
        ->assertSee('Join opens in')
        ->assertDontSee($booking->meeting_url, false);

    expect($booking->fresh()->meeting_started_at)->toBeNull();
});

it('stops accepting joins once the grace period has passed', function () {
    $scenario = classroomScenario();
    $booking = $scenario['booking'];

    $this->travelTo($booking->ends_at->addMinutes($booking->joinClosesAfterMinutes() + 1));

    $this->actingAs($scenario['student'])
        ->get(route('classroom.show', $booking))
        ->assertOk()
        ->assertSee('The classroom has closed')
        ->assertDontSee($booking->meeting_url, false);
});

it('keeps the classroom page available for a delivered lesson but without the link', function () {
    $scenario = classroomScenario();
    $booking = $scenario['booking'];

    $this->travelTo($booking->starts_at->addMinutes(10));
    app(BookingTransitionService::class)->complete($booking);

    $this->actingAs($scenario['student'])
        ->get(route('classroom.show', $booking->fresh()))
        ->assertOk()
        ->assertSee('This classroom has closed')
        ->assertDontSee($booking->meeting_url, false);
});

it('explains a failed room without leaking anything to the student', function () {
    $scenario = classroomScenario(withRoom: false);

    app(MeetingService::class)->recordFailure(
        $scenario['booking'],
        new MeetingProvisioningException('provider outage'),
    );

    $this->actingAs($scenario['student'])
        ->get(route('classroom.show', $scenario['booking']))
        ->assertOk()
        ->assertSee('The classroom could not be opened')
        ->assertSee('Our team has been notified');

    $this->actingAs($scenario['teacherUser'])
        ->get(route('classroom.show', $scenario['booking']))
        ->assertOk()
        ->assertSee('Try again');
});

it('opens a missing room on demand when the classroom page is visited in time', function () {
    $scenario = classroomScenario(withRoom: false);

    expect($scenario['booking']->meeting_status)->toBeNull();

    $this->actingAs($scenario['student'])
        ->get(route('classroom.show', $scenario['booking']))
        ->assertOk()
        ->assertSee('The classroom is open');

    expect($scenario['booking']->fresh()->meeting_status)->toBe(MeetingStatus::Ready);
});

it('does not self-heal days before the lesson', function () {
    $scenario = classroomScenario(minutesFromNow: 60 * 48, withRoom: false);

    $this->actingAs($scenario['student'])
        ->get(route('classroom.show', $scenario['booking']))
        ->assertOk()
        ->assertSee('Your classroom opens soon');

    expect($scenario['booking']->fresh()->meeting_status)->toBeNull();
});

it('keeps outsiders out of the classroom', function () {
    $scenario = classroomScenario();
    $booking = $scenario['booking'];

    $this->actingAs(User::factory()->student()->onboarded()->create())
        ->get(route('classroom.show', $booking))
        ->assertForbidden();

    $this->actingAs(User::factory()->teacher()->create())
        ->get(route('classroom.show', $booking))
        ->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('classroom.show', $booking))
        ->assertForbidden();
});

it('lets guests nowhere near the classroom', function () {
    $scenario = classroomScenario();

    $this->get(route('classroom.show', $scenario['booking']))->assertRedirect(route('login'));
});
