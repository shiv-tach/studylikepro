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
        $isStudent = $notifiable->id === $booking->student_id;
        $amount = platform_settings()->formatMinor($this->refund->amount_minor);

        $message = (new MailMessage)
            ->subject('Refund of '.$amount.' processed')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('A '.$this->refund->percent.'% refund of '.$amount.' has been issued for the lesson on '.$this->windowLabel($booking, $notifiable).'.');

        if ($this->refund->reason) {
            $message->line('Reason: '.$this->refund->reason);
        }

        return $message->line($isStudent
            ? 'It lands back on your original payment method within 5-7 working days.'
            : 'Your earnings ledger has been updated to reflect this reversal.')
            ->action(
                $isStudent ? 'View the booking' : 'Open my earnings',
                $isStudent
                    ? route('student.bookings.show', $booking)
                    : route('teacher.earnings.index'),
            );
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $booking = $this->refund->booking;
        $isStudent = $notifiable->id === $booking->student_id;

        return [
            'title' => $isStudent
                ? 'Refund processed'
                : 'Earning reversed by a refund',
            'body' => $this->refund->percent.'% refund · '.platform_settings()->formatMinor($this->refund->amount_minor),
            'url' => $isStudent
                ? route('student.bookings.show', $booking)
                : route('teacher.earnings.index'),
        ];
    }
}
