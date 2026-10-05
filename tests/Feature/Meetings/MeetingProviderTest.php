<?php

use App\Contracts\MeetingProvider;
use App\Exceptions\MeetingProvisioningException;
use App\Services\Meetings\DailyMeetingProvider;
use App\Services\Meetings\FakeMeetingProvider;
use Illuminate\Support\Facades\Http;

it('binds the offline provider by default and daily when configured', function () {
    expect(app(MeetingProvider::class)->name())->toBe('fake');

    config(['studylikepro.meeting.provider' => 'daily']);

    expect(app(MeetingProvider::class)->name())->toBe('daily');
});

it('opens a private daily room with owner and participant links', function () {
    Http::fake([
        'api.daily.co/v1/rooms' => Http::response(['name' => 'slp-9-abcdef', 'url' => 'https://team.daily.co/slp-9-abcdef']),
        'api.daily.co/v1/meeting-tokens' => Http::sequence()
            ->push(['token' => 'host-token'])
            ->push(['token' => 'member-token']),
    ]);

    $booking = classroomScenario(withRoom: false)['booking']->load(['teacherProfile.user', 'student']);

    $room = (new DailyMeetingProvider('secret-key'))->createRoom($booking);

    expect($room->externalId)->toStartWith("slp-{$booking->id}-")
        ->and($room->hostUrl)->toBe('https://team.daily.co/slp-9-abcdef?t=host-token')
        ->and($room->participantUrl)->toBe('https://team.daily.co/slp-9-abcdef?t=member-token');

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/rooms')
        && $request['privacy'] === 'private'
        && $request['properties']['exp'] === $booking->ends_at->copy()->addMinutes((int) config('studylikepro.meeting.room_ttl_after_minutes'))->timestamp
        && $request->hasHeader('Authorization', 'Bearer secret-key'));

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/meeting-tokens')
        && $request['properties']['is_owner'] === true
        && $request['properties']['user_name'] === $booking->teacherProfile->user->name);

    Http::assertSent(fn ($request) => str_ends_with($request->url(), '/meeting-tokens')
        && $request['properties']['is_owner'] === false);
});

it('reports daily failures as provisioning exceptions', function () {
    Http::fake([
        'api.daily.co/v1/rooms' => Http::response(['error' => 'invalid api key'], 401),
    ]);

    $booking = classroomScenario(withRoom: false)['booking'];

    expect(fn () => (new DailyMeetingProvider('bad-key'))->createRoom($booking))
        ->toThrow(MeetingProvisioningException::class, 'Daily could not open the classroom');
});

it('deletes the room when a daily lesson closes', function () {
    Http::fake(['api.daily.co/v1/rooms/*' => Http::response([], 200)]);

    $booking = classroomScenario()['booking'];

    (new DailyMeetingProvider('secret-key'))->endRoom($booking);

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_ends_with($request->url(), '/rooms/'.$booking->meeting_external_id));
});

it('gives the offline provider distinct host and participant links', function () {
    $booking = classroomScenario()['booking'];

    expect($booking->meeting_provider)->toBe('fake')
        ->and($booking->meeting_external_id)->toStartWith("slp-{$booking->id}-")
        ->and($booking->host_meeting_url)->toContain('t=host-')
        ->and($booking->meeting_url)->toContain('t=guest-')
        ->and($booking->host_meeting_url)->not->toBe($booking->meeting_url)
        ->and(FakeMeetingProvider::rooms())->toHaveCount(1);
});
