<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class TeacherVerificationApproved extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $channels = ['mail'];

    /**
     * @param  list<string>  $approvedSubjects  the subject names the review approved
     */
    public function __construct(protected readonly array $approvedSubjects = []) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You are verified on Studylikepro 🎉')
            ->greeting('Congratulations, '.$notifiable->name.'!')
            ->line('Your teacher profile has been reviewed and verified.')
            ->when($this->approvedSubjects !== [], fn (MailMessage $mail) => $mail
                ->line('Approved subjects: '.implode(', ', $this->approvedSubjects).'.'))
            ->line('You now appear in student search and can receive tutoring requests.')
            ->action('Go to your dashboard', route('teacher.dashboard'))
            ->line('Keep your subjects and availability up to date to attract more students.');
    }
}
