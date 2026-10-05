<?php

namespace App\Listeners;

use App\Events\RefundProcessed;
use App\Services\ConversationService;
use Illuminate\Support\Facades\DB;

class MirrorRefundInConversation
{
    public function __construct(private readonly ConversationService $conversations) {}

    public function handle(RefundProcessed $event): void
    {
        $booking = $event->refund->booking;

        if ($booking === null || ! DB::table('conversations')->where('booking_id', $booking->id)->exists()) {
            return;
        }

        $this->conversations->systemMessage(
            $booking,
            'A refund of '.platform_settings()->formatMinor($event->refund->amount_minor).' was issued for this lesson.',
        );
    }
}
