<?php

namespace App\Notifications;

use App\Models\Review;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * A teacher believes a review is unfair — support decides what happens next.
 */
class ReviewFlagged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Review $review) {}

    public function toMail(object $notifiable): MailMessage
    {
        $teacher = $this->review->teacherProfile?->user?->name ?? 'A teacher';
        $student = $this->review->reviewerName();

        return (new MailMessage)
            ->subject('Review #'.$this->review->id.' reported — '.($this->review->flagReasonLabel() ?? 'under review'))
            ->greeting('Hello '.$notifiable->name.',')
            ->line($teacher.' reported a review left by '.$student.': '.($this->review->flagReasonLabel() ?? 'no reason given').'.')
            ->line('Rating given: '.$this->review->rating.'/5')
            ->when($this->review->comment, fn (MailMessage $message) => $message->line('They wrote: "'.$this->review->comment.'"'))
            ->when($this->review->flag_notes, fn (MailMessage $message) => $message->line('Teacher notes: "'.$this->review->flag_notes.'"'))
            ->line('Hiding a review takes it off the public profile and out of the rating average.')
            ->action('Open the lesson it belongs to', route('admin.bookings.index'));
    }

    /**
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'title' => 'Review reported by '.($this->review->teacherProfile?->user?->name ?? 'a teacher'),
            'body' => $this->review->rating.'/5 · '.($this->review->flagReasonLabel() ?? 'Under review').' · review #'.$this->review->id,
            'url' => route('admin.bookings.index'),
        ];
    }
}
