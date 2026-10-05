<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Concerns\FormatsBookingTimes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class BookingCancelled extends Notification implements ShouldQueue
{
    use FormatsBookingTimes, Queueable;

    public function __construct(
        public readonly Booking $booking,
        public readonly string $by,
        public readonly ?string $reason = null,
    ) {}

    public function toMail(object $notifiable): MailMessage
    {
        $when = $this->windowLabel($this->booking, $notifiable);

        return (new MailMessage)
            ->subject('Lesson cancelled — '.$when)
            ->greeting('Hello '.$notifiable->name.',')
            ->line('The lesson on '.$when.' has been cancelled by the '.$this->by.'.')
            ->when($this->reason, fn (MailMessage $message) => $message->line('Reason: '.$this->reason))
            ->line('Nothing else is needed from you. You can book another time any time.')
            ->action(
                $notifiable->id === $this->booking->student_id ? 'Find another teacher' : 'Open my schedule',
                $notifiable->id === $this->booking->student_id
                    ? route('teachers.index')
                    : route('teacher.schedule.index'),
            );
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Lesson cancelled by the '.$this->by,
            'body' => $this->windowLabel($this->booking, $notifiable)
                .($this->reason ? ' · '.$this->reason : ''),
            'url' => $notifiable->id === $this->booking->student_id
                ? route('student.bookings.show', $this->booking)
                : route('teacher.bookings.show', $this->booking),
        ];
    }
}
