<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Concerns\FormatsBookingTimes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Tells the teacher a student just reserved one of their slots.
 */
class BookingRequestReceived extends Notification implements ShouldQueue
{
    use FormatsBookingTimes, Queueable;

    public function __construct(public readonly Booking $booking) {}

    public function toMail(object $notifiable): MailMessage
    {
        $teacher = $this->booking->teacherProfile;

        return (new MailMessage)
            ->subject('New lesson booking from '.$this->booking->student->name)
            ->greeting('Hello '.$notifiable->name.',')
            ->line($this->booking->student->name.' booked a '.$this->booking->durationMinutes().'-minute lesson with you.')
            ->line('When: '.$this->windowLabel($this->booking, $notifiable))
            ->line('Lesson: '.($this->booking->topic?->name ?? $this->booking->subject?->name ?? 'Tutoring lesson'))
            ->line('You earn '.platform_settings()->formatMinor($this->booking->teacher_payout_minor).' once the lesson is delivered.')
            ->line('The slot is held for '.platform_settings()->int('hold_ttl_minutes').' minutes while they pay. It confirms automatically.')
            ->action('Open my schedule', route('teacher.bookings.show', $this->booking));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->booking->student->name.' booked a lesson',
            'body' => $this->windowLabel($this->booking, $notifiable).' · held for '.platform_settings()->int('hold_ttl_minutes').' minutes',
            'url' => route('teacher.bookings.show', $this->booking),
        ];
    }
}
