<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * The once-a-day summary: what is on today and what is waiting in the app.
 * Mail only, and only when the user has something worth reading.
 */
class DailyDigest extends Notification implements ShouldQueue
{
    use Queueable;

    protected array $channels = ['mail'];

    /**
     * @param  list<array{title: string, detail: string}>  $lessons
     */
    public function __construct(
        public readonly array $lessons,
        public readonly int $unreadMessages,
        public readonly int $unreadNotifications,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage)
            ->subject($this->lessons === []
                ? 'Your Studylikepro day'
                : 'Today: '.count($this->lessons).' lesson'.(count($this->lessons) === 1 ? '' : 's').' on Studylikepro')
            ->greeting('Good morning, '.$notifiable->name.'!');

        if ($this->lessons !== []) {
            $message->line('On your timetable today:');
            foreach ($this->lessons as $lesson) {
                $message->line('• '.$lesson['title'].' — '.$lesson['detail']);
            }
        }

        if ($this->unreadMessages > 0) {
            $message->line($this->unreadMessages.' unread message'.($this->unreadMessages === 1 ? '' : 's').' in your lesson chats.');
        }

        if ($this->unreadNotifications > 0) {
            $message->line($this->unreadNotifications.' notification'.($this->unreadNotifications === 1 ? '' : 's').' waiting in the app.');
        }

        return $message
            ->action('Open Studylikepro', route('dashboard'))
            ->line('You get this summary on days with lessons or unread activity. Turn it off any time in Settings → Notifications.');
    }
}
