<?php

namespace App\Notifications;

use App\Models\Dispute;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Support closed a dispute. The reporter and the other party both hear the
 * outcome, worded for whichever side they are on.
 */
class DisputeResolved extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Dispute $dispute) {}

    public function toMail(object $notifiable): MailMessage
    {
        $isReporter = $notifiable->id === $this->dispute->raised_by;
        $refund = $this->dispute->refund_id !== null
            ? platform_settings()->formatMinor((int) $this->dispute->refund?->amount_minor)
            : null;

        $message = (new MailMessage)
            ->subject('Dispute #'.$this->dispute->id.' — '.strtolower($this->dispute->resolutionLabel() ?? 'closed'))
            ->greeting('Hello '.$notifiable->name.',')
            ->line('Our team finished looking at the report on your lesson (dispute #'.$this->dispute->id.').')
            ->line('Outcome: '.($this->dispute->resolutionLabel() ?? 'closed').'.');

        if ($refund !== null) {
            $message->line($isReporter
                ? 'A refund of '.$refund.' is on its way back to your original payment method (5-7 working days).'
                : 'A refund of '.$refund.' was issued to the student, and your earnings ledger has been updated.');
        }

        if ($this->dispute->resolution === Dispute::RESOLUTION_WARNED && ! $isReporter) {
            $message->line('A note from support: please keep lessons to the booked slot and stay reachable in the lesson chat. Repeated reports can lead to account suspension.');
        }

        if ($this->dispute->resolution === Dispute::RESOLUTION_SUSPENDED && ! $isReporter) {
            $message->line('Your account has been suspended while we look at this. Contact support if you believe this is a mistake.');
        }

        if ($this->dispute->resolution_notes) {
            $message->line('Support notes: "'.$this->dispute->resolution_notes.'"');
        }

        return $message->action(
            $isReporter && $this->dispute->booking ? 'View your lesson' : 'Open my dashboard',
            $isReporter && $this->dispute->booking
                ? route('student.bookings.show', $this->dispute->booking)
                : route('dashboard'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $booking = $this->dispute->booking;

        return [
            'title' => 'Dispute #'.$this->dispute->id.' closed',
            'body' => ($this->dispute->resolutionLabel() ?? 'Closed').($this->dispute->resolution_notes ? ' · '.$this->dispute->resolution_notes : ''),
            'url' => $booking !== null && $notifiable->id === $booking->student_id
                ? route('student.bookings.show', $booking)
                : route('dashboard'),
        ];
    }
}
