<?php

namespace App\Jobs;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Services\Meetings\MeetingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

/**
 * Opens the classroom for a confirmed lesson.
 *
 * Failures must never reach the payment webhook that triggered them, so the job
 * catches them, records the reason on the booking and re-queues itself with a
 * backoff until the configured attempts run out. The booking is then left in the
 * `failed` state for an admin to regenerate.
 */
class CreateBookingMeeting implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $bookingId,
        public readonly int $attempt = 1,
    ) {}

    public function handle(MeetingService $meetings): void
    {
        $booking = Booking::query()->find($this->bookingId);

        if ($booking === null || ! in_array($booking->status, [BookingStatus::Confirmed, BookingStatus::InProgress], true)) {
            return;
        }

        if ($booking->meetingIsReady()) {
            return;
        }

        try {
            $meetings->provision($booking);
        } catch (Throwable $exception) {
            $meetings->recordFailure($booking, $exception);
            $this->retryLater();
        }
    }

    /**
     * Hand the attempt to a fresh, delayed copy of this job.
     */
    private function retryLater(): void
    {
        if ($this->attempt >= (int) config('studylikepro.meeting.provision_attempts')) {
            return;
        }

        static::dispatch($this->bookingId, $this->attempt + 1)
            ->delay(now()->addSeconds($this->backoffSeconds()));
    }

    private function backoffSeconds(): int
    {
        return match ($this->attempt) {
            1 => 30,
            2 => 120,
            default => 300,
        };
    }
}
