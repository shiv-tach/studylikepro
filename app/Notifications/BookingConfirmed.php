<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Concerns\FormatsBookingTimes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class BookingConfirmed extends Notification implements ShouldQueue
{
    use FormatsBookingTimes, Queueable;

    public function __construct(public readonly Booking $booking) {}

    public function toMail(object $notifiable): MailMessage
    {
        $isStudent = $notifiable->id === $this->booking->student_id;
        $when = $this->windowLabel($this->booking, $notifiable);
        $subject = $this->booking->topic?->name ?? $this->booking->subject?->name ?? 'Tutoring lesson';

        $message = (new MailMessage)
            ->subject('Lesson confirmed — '.$when)
            ->greeting('You are all set, '.$notifiable->name.'!')
            ->line('Payment received. Your '.$this->booking->durationMinutes().'-minute lesson is confirmed.')
            ->line('When: '.$when)
            ->line('Lesson: '.$subject);

        if ($isStudent) {
            $message->line('Teacher: '.$this->booking->teacherProfile->user->name)
                ->line('Paid: '.platform_settings()->formatMinor($this->booking->price_minor))
                ->line('Your receipt is ready any time: '.route('receipts.show', $this->booking));
        } else {
            $message->line('Student: '.$this->booking->learner_name ?? $this->booking->student->name)
                ->line('You earn: '.platform_settings()->formatMinor($this->booking->teacher_payout_minor).' after the lesson is delivered');
        }

        return $message->action(
            $isStudent ? 'View my lesson' : 'Open my schedule',
            $isStudent
                ? route('student.bookings.show', $this->booking)
                : route('teacher.bookings.show', $this->booking),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $isStudent = $notifiable->id === $this->booking->student_id;

        return [
            'title' => 'Lesson confirmed for '.$this->windowLabel($this->booking, $notifiable),
            'body' => $isStudent
                ? 'With '.$this->booking->teacherProfile->user->name.' — join details arrive before the lesson.'
                : 'With '.($this->booking->learner_name ?? $this->booking->student->name).' — payment received.',
            'url' => $isStudent
                ? route('student.bookings.show', $this->booking)
                : route('teacher.bookings.show', $this->booking),
        ];
    }
}
