<?php

namespace App\Listeners;

use App\Events\PaymentFailed;
use App\Notifications\PaymentFailed as PaymentFailedNotification;

class NotifyStudentOfFailedPayment
{
    public function handle(PaymentFailed $event): void
    {
        $event->payment->student->notify(new PaymentFailedNotification(
            $event->payment,
            $event->reason ?? 'Payment failed.',
        ));
    }
}
