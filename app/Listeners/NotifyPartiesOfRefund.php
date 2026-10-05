<?php

namespace App\Listeners;

use App\Events\RefundProcessed;
use App\Notifications\LessonRefundProcessed;

class NotifyPartiesOfRefund
{
    public function handle(RefundProcessed $event): void
    {
        $refund = $event->refund;

        $refund->payment->student->notify(new LessonRefundProcessed($refund));

        $refund->booking->teacherProfile->user->notify(new LessonRefundProcessed($refund));
    }
}
