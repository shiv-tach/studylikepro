<?php

namespace App\Services\Meetings;

use App\Contracts\MeetingProvider;
use App\Enums\MeetingStatus;
use App\Models\Booking;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Owns the classroom lifecycle on a booking: opening the room, recording why an
 * attempt failed, noticing the first real join and closing the room afterwards.
 */
class MeetingService
{
    public function __construct(private readonly MeetingProvider $provider) {}

    /**
     * Open a room for a lesson. Safe to call repeatedly — an existing ready room
     * is kept unless `$force` is passed (the admin regenerate action).
     */
    public function provision(Booking $booking, bool $force = false): Booking
    {
        if (! $force && $booking->meetingIsReady()) {
            return $booking;
        }

        $room = $this->provider->createRoom($booking);

        $booking->forceFill([
            'meeting_provider' => $this->provider->name(),
            'meeting_status' => MeetingStatus::Ready,
            'meeting_external_id' => $room->externalId,
            'meeting_url' => $room->participantUrl,
            'host_meeting_url' => $room->hostUrl,
            'meeting_error' => null,
            'meeting_ended_at' => null,
        ])->save();

        return $booking;
    }

    /**
     * Record a failed attempt so the booking shows up for admins to fix.
     */
    public function recordFailure(Booking $booking, Throwable $exception): void
    {
        $booking->forceFill([
            'meeting_provider' => $booking->meeting_provider ?: $this->provider->name(),
            'meeting_status' => MeetingStatus::Failed,
            'meeting_error' => Str::limit($exception->getMessage(), 500, ''),
        ])->save();

        Log::warning('Classroom provisioning failed.', [
            'booking_id' => $booking->id,
            'error' => $exception->getMessage(),
        ]);
    }

    /**
     * Remember the first time somebody actually walked into the room — this is
     * the evidence a no-show dispute turns on.
     */
    public function markJoined(Booking $booking): void
    {
        if ($booking->meeting_started_at === null) {
            $booking->forceFill(['meeting_started_at' => now()])->save();
        }
    }

    /**
     * Close the classroom once the lesson is over. Teardown problems are logged
     * and swallowed: the room expires on its own.
     */
    public function close(Booking $booking): void
    {
        if ($booking->meeting_status === null) {
            return; // no classroom was ever opened for this booking
        }

        if ($booking->meeting_ended_at === null) {
            $booking->forceFill(['meeting_ended_at' => now()])->save();
        }

        if ($booking->meeting_external_id === null) {
            return;
        }

        try {
            $this->provider->endRoom($booking);
        } catch (Throwable $exception) {
            Log::info('Classroom teardown failed; the room will expire on its own.', [
                'booking_id' => $booking->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
