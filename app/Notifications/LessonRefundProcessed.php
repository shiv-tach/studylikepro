<?php

namespace App\Notifications;

use App\Models\Refund;
use App\Notifications\Concerns\FormatsBookingTimes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class LessonRefundProcessed extends Notification implements ShouldQueue
{
    use FormatsBookingTimes, Queueable;

    public function __construct(public readonly Refund $refund) {}

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->refund->booking;
        $purchase = $booking?->servicePurchase ?? $this->refund->payment?->purchase;
        $isStudent = $notifiable->id === ($booking?->student_id ?? $purchase?->student_id);
        $amount = platform_settings()->formatMinor($this->refund->amount_minor);

        $message = (new MailMessage)
            ->subject('Refund of '.$amount.' processed')
            ->greeting('Hello '.$notifiable->name.',');

        if ($booking !== null) {
            $message->line('A '.$this->refund->percent.'% refund of '.$amount.' has been issued for the lesson on '.$this->windowLabel($booking, $notifiable).'.');
        } elseif ($purchase !== null) {
            $message->line('A '.$this->refund->percent.'% refund of '.$amount.' has been issued for your '.$purchase->payableLabel().'.');
        } else {
            $message->line('A '.$this->refund->percent.'% refund of '.$amount.' has been issued.');
        }

        if ($this->refund->reason) {
            $message->line('Reason: '.$this->refund->reason);
        }

        return $message->line($isStudent
            ? 'It lands back on your original payment method within 5-7 working days.'
            : 'Your earnings ledger has been updated to reflect this reversal.')
            ->action(
                $isStudent ? 'View the details' : 'Open my earnings',
                $isStudent
                    ? ($booking !== null
                        ? route('student.bookings.show', $booking)
                        : ($purchase !== null ? route('student.purchases.show', $purchase) : route('dashboard')))
                    : route('teacher.earnings.index'),
            );
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $booking = $this->refund->booking;
        $purchase = $booking?->servicePurchase ?? $this->refund->payment?->purchase;
        $isStudent = $notifiable->id === ($booking?->student_id ?? $purchase?->student_id);

        return [
            'title' => $isStudent
                ? 'Refund processed'
                : 'Earning reversed by a refund',
            'body' => $this->refund->percent.'% refund · '.platform_settings()->formatMinor($this->refund->amount_minor),
            'url' => $isStudent
                ? ($booking !== null
                    ? route('student.bookings.show', $booking)
                    : ($purchase !== null ? route('student.purchases.show', $purchase) : route('dashboard')))
                : route('teacher.earnings.index'),
        ];
    }
}
