<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification as IlluminateNotification;

/**
 * Base for every Studylikepro notification.
 *
 * Channels are database + mail by default, and the mail copy is skipped for
 * users who turned email off in their settings — in-app notifications are
 * always recorded.
 */
abstract class Notification extends IlluminateNotification
{
    /**
     * Channels this notification would use when email is switched on.
     *
     * @var list<string>
     */
    protected array $channels = ['mail', 'database'];

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $wantsEmail = ! method_exists($notifiable, 'wantsEmailNotifications')
            || $notifiable->wantsEmailNotifications();

        return array_values(array_filter(
            $this->channels,
            fn (string $channel) => $channel !== 'mail' || $wantsEmail,
        ));
    }
}
