<?php

namespace App\Support\Meetings;

/**
 * A provisioned lesson room, normalised so the rest of the app never talks to a
 * specific video provider.
 */
final class MeetingRoom
{
    /**
     * @param  array<string, mixed>  $payload  raw provider response kept for auditing
     */
    public function __construct(
        public readonly string $externalId,
        public readonly string $hostUrl,
        public readonly string $participantUrl,
        public readonly array $payload = [],
    ) {}
}
