<?php

namespace App\Notifications;

use App\Models\Booking;
use App\Notifications\Concerns\FormatsBookingTimes;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sent to both sides once a lesson is delivered: the student is nudged towards
 * a review (the review form lands with the reviews milestone) and the teacher
 * hears that the payout is on the ledger.
 */
class LessonCompleted extends Notification implements ShouldQueue
{
    use FormatsBookingTimes, Queueable;

    public function __construct(public readonly Booking $booking) {}

    public function toMail(object $notifiable): MailMessage
    {
        $isStudent = $notifiable->id === $this->booking->student_id;
        $amount = platform_settings()->formatMinor($this->booking->teacher_payout_minor);

        return (new MailMessage)
            ->subject('Lesson completed — '.$this->windowLabel($this->booking, $notifiable))
            ->greeting('Well done, '.$notifiable->name.'!')
            ->when(
                $isStudent,
                fn (MailMessage $message) => $message
                    ->line('Your lesson on '.$this->windowLabel($this->booking, $notifiable).' is complete.')
                    ->line('Teacher: '.$this->booking->teacherProfile->user->name)
                    ->line('How did it go? Leave a review from the lesson page and help other students pick the right teacher — you can edit it for a week.'),
                fn (MailMessage $message) => $message
                    ->line('You delivered the lesson with '.($this->booking->learner_name ?: $this->booking->student->name).' on '.$this->windowLabel($this->booking, $notifiable).'.')
                    ->line($amount.' is on your earnings ledger and available for payout.'),
            )
            ->action(
                $isStudent ? 'View the lesson' : 'View my earnings',
                $isStudent
                    ? route('student.bookings.show', $this->booking)
                    : route('teacher.earnings.index'),
            );
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        $isStudent = $notifiable->id === $this->booking->student_id;

        return [
            'title' => 'Lesson completed',
            'body' => $isStudent
                ? 'Your lesson on '.$this->windowLabel($this->booking, $notifiable).' is complete — leave a review.'
                : 'Payout recorded for the lesson on '.$this->windowLabel($this->booking, $notifiable).'.',
            'url' => $isStudent
                ? route('student.bookings.show', $this->booking)
                : route('teacher.earnings.index'),
        ];
    }
}
