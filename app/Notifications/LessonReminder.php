<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Concerns\FormatsBookingTimes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Queued by the reminder sweep a day ahead and again shortly before a confirmed
 * lesson starts.
 */
class LessonReminder extends Notification implements ShouldQueue
{
    use FormatsBookingTimes, Queueable;

    /** The day-ahead reminder covers lessons starting inside 24 hours. */
    public const DAY_AHEAD_MINUTES = 24 * 60;

    /** From this far out the lesson is worded as "tomorrow". */
    public const TOMORROW_FROM_MINUTES = 12 * 60;

    public function __construct(
        public readonly Booking $booking,
        public readonly int $leadMinutes = 60,
    ) {}

    public function isTomorrow(): bool
    {
        return $this->leadMinutes >= self::TOMORROW_FROM_MINUTES;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $isStudent = $notifiable->id === $this->booking->student_id;
        $time = $this->windowLabel($this->booking, $notifiable, false);
        $when = $this->windowLabel($this->booking, $notifiable);

        return (new MailMessage)
            ->subject($this->isTomorrow()
                ? 'Lesson reminder — tomorrow at '.$time
                : 'Lesson reminder — '.$time.' today')
            ->greeting('See you soon, '.$notifiable->name.'!')
            ->line($this->isTomorrow()
                ? 'Your lesson is tomorrow: '.$when.'.'
                : 'Your lesson starts at '.$time.' on '.$when.'.')
            ->when(
                $isStudent,
                fn (MailMessage $message) => $message->line('Teacher: '.$this->booking->teacherProfile->user->name),
                fn (MailMessage $message) => $message->line('Student: '.($this->booking->learner_name ?? $this->booking->student->name)),
            )
            ->action(
                $isStudent ? 'Open the classroom' : 'Open your classroom',
                route('classroom.show', $this->booking),
            );
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => $this->isTomorrow() ? 'Lesson tomorrow' : 'Lesson reminder',
            'body' => ($this->isTomorrow() ? 'Tomorrow: ' : 'Starts at ').$this->windowLabel($this->booking, $notifiable, false).' on '.$this->windowLabel($this->booking, $notifiable).'.',
            'url' => route('classroom.show', $this->booking),
        ];
    }
}
