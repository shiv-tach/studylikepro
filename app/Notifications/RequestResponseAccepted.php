<?php

namespace App\Notifications;

use App\Models\RequestResponse;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class RequestResponseAccepted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly RequestResponse $response) {}

    public function toMail(object $notifiable): MailMessage
    {
        $teacher = $this->response->teacherProfile->user;
        $startsAt = $this->response->starts_at->setTimezone($notifiable->studentProfile?->timezone ?? config('studylikepro.default_display_timezone'));

        return (new MailMessage)
            ->subject($teacher->name.' accepted your request')
            ->greeting('Good news, '.$notifiable->name.'!')
            ->line($teacher->name.' can help with '.$this->response->tutoringRequest->topic->name.'.')
            ->line('Proposed time: '.$startsAt->format('D d M Y, H:i').'.')
            ->line('The slot is held for '.platform_settings()->int('hold_ttl_minutes').' minutes while you complete payment.')
            ->action('Open your request', route('student.requests.show', $this->response->tutoring_request_id));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->response->teacherProfile->user->name.' accepted your request',
            'body' => 'Slot held for '.platform_settings()->int('hold_ttl_minutes').' minutes — complete payment to confirm.',
            'url' => route('student.requests.show', $this->response->tutoring_request_id),
        ];
    }
}
