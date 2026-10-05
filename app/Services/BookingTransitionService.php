<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\RequestStatus;
use App\Events\BookingCancelled;
use App\Events\BookingCompleted;
use App\Events\BookingConfirmed;
use App\Events\BookingExpired;
use App\Models\Booking;
use App\Services\Meetings\MeetingService;
use Illuminate\Validation\ValidationException;

/**
 * The booking state machine: which status changes are legal, the time windows
 * around them, and the events each transition publishes.
 *
 * Role authorisation lives in BookingPolicy; this service only guards state.
 */
class BookingTransitionService
{
    public function __construct(
        private readonly PlatformSettings $settings,
        private readonly MeetingService $meetings,
    ) {}

    /**
     * Legal target statuses per current status.
     *
     * @var array<string, list<string>>
     */
    public const TRANSITIONS = [
        BookingStatus::PendingPayment->value => [
            BookingStatus::Confirmed->value,
            BookingStatus::Cancelled->value,
            BookingStatus::Expired->value,
        ],
        BookingStatus::Confirmed->value => [
            BookingStatus::InProgress->value,
            BookingStatus::Completed->value,
            BookingStatus::Cancelled->value,
            BookingStatus::NoShow->value,
            BookingStatus::Disputed->value,
        ],
        BookingStatus::InProgress->value => [
            BookingStatus::Completed->value,
            BookingStatus::NoShow->value,
            BookingStatus::Disputed->value,
        ],
        BookingStatus::Completed->value => [
            BookingStatus::Disputed->value,
        ],
        BookingStatus::Disputed->value => [
            BookingStatus::Resolved->value,
            BookingStatus::Completed->value,
        ],
        BookingStatus::Resolved->value => [],
        BookingStatus::Cancelled->value => [],
        BookingStatus::Expired->value => [],
        BookingStatus::NoShow->value => [],
    ];

    public function canTransition(BookingStatus $from, BookingStatus $to): bool
    {
        return in_array($to->value, self::TRANSITIONS[$from->value] ?? [], true);
    }

    /**
     * @return list<BookingStatus>
     */
    public function transitionsFrom(BookingStatus $from): array
    {
        return array_map(
            fn (string $status) => BookingStatus::from($status),
            self::TRANSITIONS[$from->value] ?? [],
        );
    }

    /**
     * Confirm the hold once payment is captured. Safe to call twice: a retried
     * gateway webhook on an already-confirmed booking is a no-op.
     */
    public function confirm(Booking $booking): Booking
    {
        if ($booking->status === BookingStatus::Confirmed) {
            return $booking;
        }

        if ($booking->status === BookingStatus::PendingPayment && $this->hasLapsedHold($booking)) {
            $this->expire($booking);

            throw ValidationException::withMessages([
                'status' => __('This hold has expired — please book the slot again.'),
            ]);
        }

        $this->assertTransition($booking, BookingStatus::Confirmed);

        $booking->forceFill([
            'status' => BookingStatus::Confirmed,
            'confirmed_at' => now(),
            'expires_at' => null,
        ])->save();

        BookingConfirmed::dispatch($booking);

        return $booking;
    }

    /**
     * Mark the lesson as running. The teacher may open the room a little early.
     */
    public function start(Booking $booking): Booking
    {
        $this->assertTransition($booking, BookingStatus::InProgress);

        $opensAt = $booking->starts_at
            ->copy()
            ->subMinutes((int) config('studylikepro.booking.teacher_start_early_minutes'));

        if (now()->lessThan($opensAt)) {
            throw ValidationException::withMessages([
                'status' => __('You can start the lesson from :minutes minutes before its start time.', [
                    'minutes' => config('studylikepro.booking.teacher_start_early_minutes'),
                ]),
            ]);
        }

        $booking->forceFill([
            'status' => BookingStatus::InProgress,
            'started_at' => $booking->started_at ?? now(),
        ])->save();

        return $booking;
    }

    /**
     * Lesson delivered, by the teacher or by the auto-completion sweep.
     */
    public function complete(Booking $booking, bool $automatic = false): Booking
    {
        $this->assertTransition($booking, BookingStatus::Completed);

        // A lesson that has not started yet cannot be delivered; once the teacher
        // has opened the classroom, they can close it whenever the lesson ends.
        if (! $automatic && $booking->started_at === null && now()->lessThan($booking->starts_at)) {
            throw ValidationException::withMessages([
                'status' => __('This lesson has not started yet.'),
            ]);
        }

        $booking->forceFill([
            'status' => BookingStatus::Completed,
            'started_at' => $booking->started_at ?? $booking->starts_at,
            'completed_at' => now(),
        ])->save();

        if ($booking->tutoringRequest && $booking->tutoringRequest->status === RequestStatus::Matched) {
            $booking->tutoringRequest->update(['status' => RequestStatus::Closed]);
        }

        $this->meetings->close($booking);

        BookingCompleted::dispatch($booking, $automatic);

        return $booking;
    }

    public function markNoShow(Booking $booking): Booking
    {
        $this->assertTransition($booking, BookingStatus::NoShow);

        if (now()->lessThan($booking->starts_at)) {
            throw ValidationException::withMessages([
                'status' => __('This lesson has not started yet.'),
            ]);
        }

        $booking->forceFill(['status' => BookingStatus::NoShow])->save();

        $this->meetings->close($booking);

        return $booking;
    }

    /**
     * @param  string  $by  one of Booking::CANCELLED_BY_*
     */
    public function cancel(Booking $booking, string $by, ?string $reason = null): Booking
    {
        $this->assertTransition($booking, BookingStatus::Cancelled);

        if ($by === Booking::CANCELLED_BY_STUDENT
            && $booking->status === BookingStatus::Confirmed
            && $booking->isInsideStudentCancelWindow($this->settings->int('student_cancel_window_hours'))) {
            throw ValidationException::withMessages([
                'status' => __('It is less than :hours hours before this lesson — contact support to cancel.', [
                    'hours' => $this->settings->int('student_cancel_window_hours'),
                ]),
            ]);
        }

        $booking->forceFill([
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now(),
            'cancelled_by' => $by,
            'cancellation_reason' => $reason,
        ])->save();

        // An abandoned lesson puts the student's question back in front of teachers.
        $request = $booking->tutoringRequest;

        if ($request
            && $request->status === RequestStatus::Matched
            && $request->expires_at?->isFuture()) {
            $request->update(['status' => RequestStatus::Open]);
        }

        $this->meetings->close($booking);

        BookingCancelled::dispatch($booking, $by, $reason);

        return $booking;
    }

    /**
     * The payment window lapsed, so the slot goes back on the market.
     */
    public function expire(Booking $booking): Booking
    {
        $this->assertTransition($booking, BookingStatus::Expired);

        $booking->forceFill(['status' => BookingStatus::Expired])->save();

        BookingExpired::dispatch($booking);

        return $booking;
    }

    public function hasLapsedHold(Booking $booking): bool
    {
        return $booking->status === BookingStatus::PendingPayment
            && $booking->expires_at !== null
            && $booking->expires_at->isPast();
    }

    public function assertTransition(Booking $booking, BookingStatus $to): void
    {
        if ($this->canTransition($booking->status, $to)) {
            return;
        }

        throw ValidationException::withMessages([
            'status' => __('A booking that is :from cannot become :to.', [
                'from' => strtolower($booking->status->label()),
                'to' => strtolower($to->label()),
            ]),
        ]);
    }
}
