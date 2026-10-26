<?php

namespace App\Listeners;

use App\Events\ManualPaymentRejected;
use App\Notifications\ManualPaymentRejected as ManualPaymentRejectedNotification;

class NotifyStudentOfRejectedSlip
{
    public function handle(ManualPaymentRejected $event): void
    {
        $event->payment->student->notify(new ManualPaymentRejectedNotification(
            $event->payment,
            $event->reason,
        ));
    }
}
