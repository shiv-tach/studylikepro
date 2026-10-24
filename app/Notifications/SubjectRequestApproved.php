<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class SubjectRequestApproved extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $channels = ['mail'];

    public function __construct(public string $subjectName) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Subject approved on Studylikepro ✅')
            ->greeting('Good news, '.$notifiable->displayName().'!')
            ->line('Our team approved your request to teach '.$this->subjectName.'.')
            ->line('Students can now find you through it. Set its lessons, grades and rates on your Subjects page.')
            ->action('Open my subjects', route('teacher.subjects.index'))
            ->line('Keep your subjects and availability up to date to attract more students.');
    }
}
