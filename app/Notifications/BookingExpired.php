<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Concerns\FormatsBookingTimes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class BookingExpired extends Notification implements ShouldQueue
{
    use FormatsBookingTimes, Queueable;

    public function __construct(public readonly Booking $booking) {}

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your held slot was released')
            ->greeting('Hello '.$notifiable->name.',')
            ->line('The slot on '.$this->windowLabel($this->booking, $notifiable).' was held for '.platform_settings()->int('hold_ttl_minutes').' minutes and the payment did not arrive.')
            ->line('It is back on the market and other students can take it.')
            ->action('Book another time', route('teachers.show', $this->booking->teacherProfile));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Held slot released',
            'body' => 'Payment did not arrive for '.$this->windowLabel($this->booking, $notifiable).'.',
            'url' => route('student.bookings.show', $this->booking),
        ];
    }
}
