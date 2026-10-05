<?php

namespace App\Listeners;

use App\Events\BookingCancelled;
use App\Services\ConversationService;
use Illuminate\Support\Facades\DB;

class MirrorBookingCancelledInConversation
{
    public function __construct(private readonly ConversationService $conversations) {}

    public function handle(BookingCancelled $event): void
    {
        $booking = $event->booking;

        // Only lessons that ever got a thread: an unpaid hold never had one.
        if (! DB::table('conversations')->where('booking_id', $booking->id)->exists()) {
            return;
        }

        $who = ucfirst((string) $booking->cancelled_by) ?: 'Someone';

        $this->conversations->systemMessage(
            $booking,
            $who.' cancelled this lesson.'.($booking->cancellation_reason ? ' Reason: "'.$booking->cancellation_reason.'"' : ''),
        );
    }
}
