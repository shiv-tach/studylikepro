<?php

namespace App\Listeners;

use App\Events\BookingCompleted;
use App\Services\ConversationService;

class MirrorBookingCompletedInConversation
{
    public function __construct(private readonly ConversationService $conversations) {}

    public function handle(BookingCompleted $event): void
    {
        $booking = $event->booking;

        $this->conversations->systemMessage(
            $booking,
            $event->automatic
                ? 'This lesson was marked as delivered automatically after its end time.'
                : 'Lesson delivered. Thanks for learning with Studylikepro — you can still use this thread for follow-up questions.',
        );
    }
}
