<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class TeacherVerificationRejected extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $reason) {}

    protected array $channels = ['mail'];

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Action needed on your Studylikepro application')
            ->greeting('Hi '.$notifiable->name.',')
            ->line('Our team reviewed your teacher application and could not approve it yet.')
            ->line('Reviewer notes:')
            ->line('"'.$this->reason.'"')
            ->line('Update your documents or profile details, then resubmit for review.')
            ->action('Update my application', route('teacher.verification'))
            ->line('If you believe this was a mistake, reply to this email and we will take another look.');
    }
}
