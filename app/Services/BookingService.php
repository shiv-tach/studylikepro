<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Events\BookingCreated;
use App\Models\Booking;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Support\BookingDraft;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Creates booking holds and answers "what can be booked, at what price".
 *
 * Every hold is written inside a transaction that locks the teacher row, so two
 * simultaneous requests for the same slot cannot both pass the overlap check.
 */
class BookingService
{
    public function __construct(
        private readonly SlotService $slots,
        private readonly PlatformSettings $settings,
        private readonly BookingFeeService $fees,
    ) {}

    /**
     * Open slots grouped by the viewer's local date.
     *
     * @return array<string, list<array{starts_at: CarbonImmutable, ends_at: CarbonImmutable, label: string}>>
     */
    public function availableSlots(
        TeacherProfile $teacher,
        int $durationMinutes,
        ?string $timezone = null,
        ?int $days = null,
        int $limit = 200,
    ): array {
        $timezone = $timezone ?: config('studylikepro.default_display_timezone');
        $days = $days ?? (int) config('studylikepro.booking.max_advance_days');

        $from = CarbonImmutable::now();
        $until = $from->addDays($days);

        $slots = $this->slots->openSlots(
            $teacher,
            $from,
            $until,
            $durationMinutes,
            $this->slots->blockedRanges($teacher, $from, $until),
            $limit,
        );

        $grouped = [];

        foreach ($slots as $slot) {
            $label = $slot['starts_at']->setTimezone($timezone);

            $grouped[$label->toDateString()][] = [
                'starts_at' => $slot['starts_at'],
                'ends_at' => $slot['ends_at'],
                'label' => $label->format('H:i'),
            ];
        }

        return $grouped;
    }

    /**
     * The price a student pays for a lesson of this length, in minor units.
     */
    public function priceMinor(TeacherProfile $teacher, ?Subject $subject, int $durationMinutes): int
    {
        return (int) round($teacher->effectiveRateFor($subject) * $durationMinutes / 60);
    }

    /**
     * Split a price into the platform fee and the teacher's payout.
     *
     * @return array{commission_percent: int, platform_fee_minor: int, teacher_payout_minor: int}
     */
    public function feeBreakdown(int $priceMinor): array
    {
        $commission = $this->settings->int('commission_percent');
        $fee = (int) round($priceMinor * $commission / 100);

        return [
            'commission_percent' => $commission,
            'platform_fee_minor' => $fee,
            'teacher_payout_minor' => $priceMinor - $fee,
        ];
    }

    /**
     * Reserve a slot as a pending-payment hold.
     */
    public function reserve(BookingDraft $draft): Booking
    {
        $startsAt = CarbonImmutable::instance($draft->startsAt)->utc();
        $endsAt = CarbonImmutable::instance($draft->endsAt)->utc();
        $duration = (int) $startsAt->diffInMinutes($endsAt);

        if ($startsAt->lessThanOrEqualTo(CarbonImmutable::now())) {
            throw ValidationException::withMessages([
                'starts_at' => __('Pick a slot that is still in the future.'),
            ]);
        }

        $booking = DB::transaction(function () use ($draft, $startsAt, $endsAt, $duration) {
            // Serialise concurrent bookings for this teacher; without the parent-row
            // lock two inserts could both pass the overlap check below.
            TeacherProfile::query()->whereKey($draft->teacher->id)->lockForUpdate()->first();

            $taken = Booking::query()
                ->where('teacher_profile_id', $draft->teacher->id)
                ->blockingSlot()
                ->overlapping($startsAt, $endsAt)
                ->exists();

            if ($taken) {
                throw ValidationException::withMessages([
                    'starts_at' => __('That slot was just taken — please pick another time.'),
                ]);
            }

            if (! $this->slots->isAvailableAt($draft->teacher, $startsAt, $duration)) {
                throw ValidationException::withMessages([
                    'starts_at' => __('That time is outside the teacher availability for this lesson length.'),
                ]);
            }

            $price = $draft->priceMinor ?? $this->priceMinor($draft->teacher, $draft->subject, $duration);

            // The student-facing fee is snapshotted here, together with the
            // special offer that shaped it, so checkout never re-prices a hold.
            $fee = $this->fees->quote();

            return Booking::query()->create([
                'student_id' => $draft->student->id,
                'teacher_profile_id' => $draft->teacher->id,
                'tutoring_request_id' => $draft->tutoringRequest?->id,
                'subject_id' => $draft->subject?->id,
                'topic_id' => $draft->topic?->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => BookingStatus::PendingPayment,
                'price_minor' => $price,
                'currency' => (string) config('studylikepro.currency'),
                'learner_name' => $draft->learnerName,
                'learner_grade' => $draft->learnerGrade,
                'expires_at' => now()->addMinutes($this->settings->int('hold_ttl_minutes')),
                ...$this->feeBreakdown($price),
                'booking_fee_minor' => $fee['booking_fee_minor'],
                'booking_fee_discount_minor' => $fee['discount_minor'],
                'booking_fee_promotion_id' => $fee['promotion']?->id,
            ]);
        });

        BookingCreated::dispatch($booking);

        return $booking;
    }
}
