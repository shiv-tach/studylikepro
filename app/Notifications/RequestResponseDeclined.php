<?php

namespace App\Notifications;

use App\Models\RequestResponse;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class RequestResponseDeclined extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly RequestResponse $response) {}

    public function toMail(object $notifiable): MailMessage
    {
        $teacher = $this->response->teacherProfile->user;

        return (new MailMessage)
            ->subject($teacher->name.' cannot take your request')
            ->greeting('Hi '.$notifiable->name.',')
            ->line($teacher->name.' is not available for '.$this->response->tutoringRequest->lesson->name.' right now.')
            ->when($this->response->message, fn (MailMessage $mail) => $mail->line('Message: '.$this->response->message))
            ->line('We will keep matching your request with other verified teachers.')
            ->action('View your request', route('student.requests.show', $this->response->tutoring_request_id));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->response->teacherProfile->user->name.' declined your request',
            'body' => 'Still looking for a teacher for '.$this->response->tutoringRequest->lesson->name.'.',
            'url' => route('student.requests.show', $this->response->tutoring_request_id),
        ];
    }
}
