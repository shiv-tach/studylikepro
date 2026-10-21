<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class SubjectRequestRejected extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $channels = ['mail'];

    public function __construct(public string $subjectLabel, public string $reason) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your subject request needs a change')
            ->greeting('Hi '.$notifiable->name.',')
            ->line('Our team could not approve your request for '.$this->subjectLabel.'.')
            ->line('Reviewer notes:')
            ->line('"'.$this->reason.'"')
            ->line('You can send a new request from your Subjects page.')
            ->action('Open my subjects', route('teacher.subjects.index'))
            ->line('If you believe this was a mistake, reply to this email and we will take another look.');
    }
}
