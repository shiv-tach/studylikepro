<?php

namespace App\Services\Meetings;

use App\Contracts\MeetingProvider;
use App\Exceptions\MeetingProvisioningException;
use App\Models\Booking;
use App\Support\Meetings\MeetingRoom;
use Illuminate\Support\Str;

/**
 * Offline provider used for local development and tests. Rooms look like real
 * join links but nothing leaves the machine.
 *
 * Tests can inspect `rooms()`, count `attempts()` and force failures with
 * `fail()`. State is reset before every test in tests/Pest.php.
 */
class FakeMeetingProvider implements MeetingProvider
{
    /** @var list<array{booking_id: int, external_id: string, host_url: string, participant_url: string}> */
    private static array $rooms = [];

    /** @var list<string> */
    private static array $ended = [];

    private static int $attempts = 0;

    private static bool $failing = false;

    public static function fail(bool $failing = true): void
    {
        self::$failing = $failing;
    }

    /**
     * @return list<array{booking_id: int, external_id: string, host_url: string, participant_url: string}>
     */
    public static function rooms(): array
    {
        return self::$rooms;
    }

    /**
     * @return list<string> external ids of rooms that were torn down
     */
    public static function endedRooms(): array
    {
        return self::$ended;
    }

    public static function attempts(): int
    {
        return self::$attempts;
    }

    public static function reset(): void
    {
        self::$rooms = [];
        self::$ended = [];
        self::$attempts = 0;
        self::$failing = false;
    }

    public function name(): string
    {
        return 'fake';
    }

    public function createRoom(Booking $booking): MeetingRoom
    {
        self::$attempts++;

        if (self::$failing) {
            throw new MeetingProvisioningException('The demo meeting provider is unavailable.');
        }

        $slug = 'slp-'.$booking->id.'-'.Str::lower(Str::random(6));
        $url = 'https://demo.daily.test/'.$slug;

        $room = [
            'booking_id' => $booking->id,
            'external_id' => $slug,
            'host_url' => $url.'?t=host-'.Str::lower(Str::random(8)),
            'participant_url' => $url.'?t=guest-'.Str::lower(Str::random(8)),
        ];

        self::$rooms[] = $room;

        return new MeetingRoom(
            externalId: $room['external_id'],
            hostUrl: $room['host_url'],
            participantUrl: $room['participant_url'],
            payload: ['provider' => 'fake'],
        );
    }

    public function endRoom(Booking $booking): void
    {
        if ($booking->meeting_external_id) {
            self::$ended[] = $booking->meeting_external_id;
        }
    }
}
