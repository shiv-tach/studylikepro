<?php

namespace App\Services\Meetings;

use App\Contracts\MeetingProvider;
use App\Exceptions\MeetingProvisioningException;
use App\Models\Booking;
use App\Support\Meetings\MeetingRoom;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Daily.co rooms: one private room per lesson, an owner link for the teacher and
 * a plain participant link for the student. Rooms expire on their own shortly
 * after the lesson, so no cleanup is required if teardown fails.
 *
 * @see https://docs.daily.co/reference/rest-api/rooms
 */
class DailyMeetingProvider implements MeetingProvider
{
    public function __construct(
        private readonly ?string $apiKey = null,
        private readonly string $baseUrl = 'https://api.daily.co/v1',
        private readonly int $timeout = 20,
    ) {}

    public function name(): string
    {
        return 'daily';
    }

    public function createRoom(Booking $booking): MeetingRoom
    {
        $expiresAt = $booking->ends_at
            ->copy()
            ->addMinutes((int) config('studylikepro.meeting.room_ttl_after_minutes'))
            ->timestamp;

        $name = 'slp-'.$booking->id.'-'.Str::lower(Str::random(6));

        try {
            $room = $this->request()
                ->post('/rooms', [
                    'name' => $name,
                    'privacy' => 'private',
                    'properties' => [
                        'exp' => $expiresAt,
                        'enable_chat' => true,
                        'enable_screenshare' => true,
                        'eject_at_room_exp' => true,
                    ],
                ])
                ->throw()
                ->json();

            $url = (string) ($room['url'] ?? "{$this->baseUrl}/rooms/{$name}");

            $hostToken = $this->mintToken($name, $this->hostName($booking), isOwner: true, expiresAt: $expiresAt);
            $participantToken = $this->mintToken($name, $this->participantName($booking), isOwner: false, expiresAt: $expiresAt);
        } catch (RequestException $exception) {
            throw new MeetingProvisioningException(
                'Daily could not open the classroom: '.$exception->getMessage(),
                previous: $exception,
            );
        }

        return new MeetingRoom(
            externalId: $name,
            hostUrl: $this->joinUrl($url, $hostToken),
            participantUrl: $this->joinUrl($url, $participantToken),
            payload: ['provider' => 'daily', 'room' => $name, 'exp' => $expiresAt],
        );
    }

    public function endRoom(Booking $booking): void
    {
        if (! $booking->meeting_external_id) {
            return;
        }

        try {
            $this->request()->delete('/rooms/'.$booking->meeting_external_id);
        } catch (RequestException) {
            // The room expires on its own; a failed teardown must never break the lesson.
        }
    }

    private function mintToken(string $room, string $userName, bool $isOwner, int $expiresAt): string
    {
        $response = $this->request()
            ->post('/meeting-tokens', [
                'properties' => [
                    'room_name' => $room,
                    'is_owner' => $isOwner,
                    'user_name' => $userName,
                    'exp' => $expiresAt,
                ],
            ])
            ->throw()
            ->json();

        return (string) ($response['token'] ?? '');
    }

    private function joinUrl(string $roomUrl, string $token): string
    {
        return $token === '' ? $roomUrl : $roomUrl.'?t='.$token;
    }

    private function hostName(Booking $booking): string
    {
        return $booking->teacherProfile?->user?->name ?? 'Teacher';
    }

    private function participantName(Booking $booking): string
    {
        return $booking->learner_name ?: ($booking->student?->name ?? 'Student');
    }

    private function request(): PendingRequest
    {
        return Http::withToken((string) $this->apiKey)
            ->baseUrl($this->baseUrl)
            ->timeout($this->timeout)
            ->acceptJson();
    }
}
