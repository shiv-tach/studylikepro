<?php

namespace App\Listeners;

use App\Events\PayoutPaid;
use App\Notifications\PayoutPaid as PayoutPaidNotification;

class NotifyTeacherOfPayout
{
    public function handle(PayoutPaid $event): void
    {
        $event->payout->teacherProfile->user->notify(new PayoutPaidNotification($event->payout));
    }
}
