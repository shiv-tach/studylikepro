<?php

namespace App\Notifications;

use App\Models\Dispute;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Somebody reported a problem with a lesson — support needs to look at it.
 */
class DisputeRaised extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Dispute $dispute) {}

    public function toMail(object $notifiable): MailMessage
    {
        $booking = $this->dispute->booking;
        $who = $this->dispute->raisedBy?->name ?? 'A user';

        return (new MailMessage)
            ->subject('New dispute #'.$this->dispute->id.' — '.$this->dispute->reasonLabel())
            ->greeting('Hello '.$notifiable->name.',')
            ->line($who.' raised a dispute: '.$this->dispute->reasonLabel().'.')
            ->when($booking, fn (MailMessage $message) => $message->line('Lesson: #'.str_pad((string) $booking->id, 6, '0', STR_PAD_LEFT).' · '.($booking->subject?->name ?? 'Tutoring lesson')))
            ->when($this->dispute->details, fn (MailMessage $message) => $message->line('They wrote: "'.$this->dispute->details.'"'))
            ->line('Open the bookings console to review the lesson, its payments and the chat before deciding what to do.')
            ->action('Review the booking', route('admin.bookings.index'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Dispute #'.$this->dispute->id.': '.$this->dispute->reasonLabel(),
            'body' => 'Raised by '.($this->dispute->raisedBy?->name ?? 'a user').($this->dispute->booking_id ? ' · booking #'.str_pad((string) $this->dispute->booking_id, 6, '0', STR_PAD_LEFT) : ''),
            'url' => route('admin.bookings.index'),
        ];
    }
}
