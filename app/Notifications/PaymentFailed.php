<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The gateway refused the payment; the student can retry while the hold lasts.
 */
class PaymentFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Payment $payment, public readonly string $reason) {}

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->payment->booking;

        $message = (new MailMessage)
            ->subject('Payment failed for '.platform_settings()->formatMinor($this->payment->amount_minor))
            ->greeting('Hello '.$notifiable->name.',')
            ->line('We could not complete the payment for your '.$booking->durationMinutes().'-minute lesson.')
            ->line('What the gateway said: "'.$this->reason.'"');

        if ($booking->expires_at?->isFuture()) {
            $message->line('Your slot is still held until '.$booking->expires_at->format('H:i').' — you can try again with another method.');
        } else {
            $message->line('The slot has been released. Book it again and pay with another method.');
        }

        return $message->action('Try the payment again', route('student.bookings.show', $booking));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Payment failed',
            'body' => platform_settings()->formatMinor($this->payment->amount_minor).' — '.$this->reason,
            'url' => route('student.bookings.show', $this->payment->booking_id),
        ];
    }
}
