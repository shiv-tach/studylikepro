<?php

namespace App\Listeners;

use App\Events\BookingConfirmed;
use App\Services\ConversationService;

/**
 * Keeps the lesson chat in step with the booking: when payment lands, the
 * thread opens with a confirmation note.
 */
class MirrorBookingConfirmedInConversation
{
    public function __construct(private readonly ConversationService $conversations) {}

    public function handle(BookingConfirmed $event): void
    {
        $booking = $event->booking;

        $this->conversations->systemMessage(
            $booking,
            'Payment received — your lesson on '.$booking->starts_at->format('D d M Y, H:i').' is confirmed. Use this chat to share what you want to cover, files or joining notes.',
        );
    }
}
