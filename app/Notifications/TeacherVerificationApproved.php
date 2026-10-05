<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class TeacherVerificationApproved extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $channels = ['mail'];

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You are verified on Studylikepro 🎉')
            ->greeting('Congratulations, '.$notifiable->name.'!')
            ->line('Your teacher profile has been reviewed and verified.')
            ->line('You now appear in student search and can receive tutoring requests.')
            ->action('Go to your dashboard', route('teacher.dashboard'))
            ->line('Keep your subjects and availability up to date to attract more students.');
    }
}
