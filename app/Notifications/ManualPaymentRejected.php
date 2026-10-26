<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Support\TimeFormat;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The admin could not verify the uploaded payment slip. The student can upload
 * a new one while the hold is still running.
 */
class ManualPaymentRejected extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Payment $payment,
        public readonly string $reason,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        $amount = platform_settings()->formatMinor($this->payment->amount_minor);

        $message = (new MailMessage)
            ->subject('We could not verify your payment of '.$amount)
            ->greeting('Hello '.$notifiable->displayName().',')
            ->line('We checked the slip you uploaded for '.$amount.' but could not match it to a deposit.')
            ->line('Reason: "'.$this->reason.'"')
            ->line('You can upload a clearer slip, or pay with another method, while the slot is still held.');

        $expiresAt = $this->payment->booking?->expires_at ?? $this->payment->purchase?->expires_at;

        if ($expiresAt?->isFuture()) {
            $message->line('Your slot is held until '.$expiresAt->format(TimeFormat::DATE_TIME).'.');
        } else {
            $message->line('The slot may already be released — start the checkout again if it has gone.');
        }

        return $message->action('Upload the slip again', $this->checkoutUrl());
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Payment slip rejected',
            'body' => platform_settings()->formatMinor($this->payment->amount_minor).' — '.$this->reason,
            'url' => $this->checkoutUrl(),
        ];
    }

    private function checkoutUrl(): string
    {
        return $this->payment->booking_id !== null
            ? route('student.bookings.show', $this->payment->booking_id)
            : route('student.purchases.show', $this->payment->service_purchase_id);
    }
}
