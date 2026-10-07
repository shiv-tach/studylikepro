<?php

namespace App\Notifications;

use App\Models\TutoringRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class RequestPublished extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly TutoringRequest $request) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('New tutoring request matches your subjects')
            ->greeting('Hi '.$notifiable->name.',')
            ->line('A student needs help with **'.$this->request->lesson->name.'** ('.$this->request->subject->name.').')
            ->line('They are free: '.implode(' · ', $this->request->windowLabels()))
            ->line('Respond first and the slot is held for the student to pay.')
            ->action('View the request', route('teacher.requests.index'))
            ->line('Requests expire after seven days.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'New request: '.$this->request->lesson->name,
            'body' => $this->request->subject->name.' · '.implode(' · ', $this->request->windowLabels()),
            'url' => route('teacher.requests.index'),
        ];
    }
}
