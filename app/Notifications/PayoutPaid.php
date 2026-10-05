<?php

namespace App\Notifications;

use App\Models\Payout;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The teacher's batched earnings have left the bank.
 */
class PayoutPaid extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Payout $payout) {}

    public function toMail(object $notifiable): MailMessage
    {
        $amount = platform_settings()->formatMinor($this->payout->amount_minor);

        return (new MailMessage)
            ->subject('Payout of '.$amount.' sent')
            ->greeting('Good news, '.$notifiable->name.'!')
            ->line('We have transferred '.$amount.' for '.$this->payout->lessons_count.' lesson(s) to your registered account.')
            ->line('Transfer reference: '.$this->payout->reference)
            ->line('Batches are cleaned up in the earnings ledger — the lessons now show as paid out.')
            ->action('Open my earnings', route('teacher.earnings.index'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Payout sent',
            'body' => platform_settings()->formatMinor($this->payout->amount_minor).' · '.$this->payout->lessons_count.' lesson(s) · '.$this->payout->reference,
            'url' => route('teacher.earnings.index'),
        ];
    }
}
