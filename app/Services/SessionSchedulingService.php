<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\ServiceType;
use App\Events\BookingCreated;
use App\Models\Booking;
use App\Models\ServicePurchase;
use App\Models\TeacherProfile;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Books live sessions against an already-paid purchase: lesson-plan sessions
 * (one or several customised sessions per allocation), Q&A sessions and paper
 * discussion sessions. Sessions carry no money of their own — the purchase is
 * the payable unit — so they are created directly as confirmed bookings.
 */
class SessionSchedulingService
{
    public function __construct(
        private readonly SlotService $slots,
        private readonly PurchaseService $purchases,
    ) {}

    /**
     * Reserve one session of the purchase at the given time.
     */
    public function reserve(ServicePurchase $purchase, CarbonInterface $startsAt, int $durationMinutes): Booking
    {
        $startsAt = CarbonImmutable::instance($startsAt)->utc();
        $endsAt = $startsAt->addMinutes($durationMinutes);

        if ($purchase->type() === ServiceType::Lesson) {
            $this->assertLessonPlanConstraints($purchase, $durationMinutes);
        }

        if ($startsAt->lessThanOrEqualTo(CarbonImmutable::now())) {
            throw ValidationException::withMessages([
                'starts_at' => __('Pick a session time that is still in the future.'),
            ]);
        }

        $teacher = $purchase->teacherProfile;

        $booking = DB::transaction(function () use ($purchase, $teacher, $startsAt, $endsAt, $durationMinutes) {
            // Serialise concurrent reservations for this teacher, mirroring
            // BookingService::reserve so two requests cannot double-book a slot.
            TeacherProfile::query()->whereKey($teacher->id)->lockForUpdate()->first();

            $taken = Booking::query()
                ->where('teacher_profile_id', $teacher->id)
                ->blockingSlot()
                ->overlapping($startsAt, $endsAt)
                ->exists();

            if ($taken) {
                throw ValidationException::withMessages([
                    'starts_at' => __('That slot was just taken — please pick another time.'),
                ]);
            }

            if (! $this->slots->isAvailableAt($teacher, $startsAt, $durationMinutes)) {
                throw ValidationException::withMessages([
                    'starts_at' => __('That time is outside the teacher availability for this session length.'),
                ]);
            }

            return Booking::query()->create([
                'student_id' => $purchase->student_id,
                'teacher_profile_id' => $teacher->id,
                'subject_id' => $purchase->subject_id,
                'lesson_id' => $purchase->lesson_id,
                'service_purchase_id' => $purchase->id,
                'service_type' => $purchase->service_type->value,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                // The purchase already settled the money; the session itself is free.
                'status' => BookingStatus::Confirmed,
                'price_minor' => 0,
                'commission_percent' => 0,
                'platform_fee_minor' => 0,
                'teacher_payout_minor' => 0,
                'booking_fee_minor' => 0,
                'booking_fee_discount_minor' => 0,
                'currency' => $purchase->currency,
            ]);
        });

        if ($purchase->isLessonPlan()) {
            $this->purchases->syncDelivery($purchase);
        }

        BookingCreated::dispatch($booking);

        return $booking;
    }

    /**
     * Open slots for the teacher that this plan can still consume, grouped by
     * the viewer's local date.
     *
     * @return array<string, list<array{starts_at: CarbonImmutable, ends_at: CarbonImmutable, label: string}>>
     */
    public function availableSlots(ServicePurchase $purchase, ?int $durationMinutes = null, ?string $timezone = null): array
    {
        $teacher = $purchase->teacherProfile;
        $timezone = $timezone ?: config('studylikepro.default_display_timezone');
        $duration = $durationMinutes ?? $this->defaultSessionMinutes($purchase);

        $from = CarbonImmutable::now();
        $until = $purchase->expires_at
            ? CarbonImmutable::instance($purchase->expires_at)
            : $from->addDays((int) config('studylikepro.booking.max_advance_days'));

        $slots = $this->slots->openSlots(
            $teacher,
            $from,
            $until,
            $duration,
            $this->slots->blockedRanges($teacher, $from, $until),
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

    public function defaultSessionMinutes(ServicePurchase $purchase): int
    {
        $offeringMinutes = (int) ($purchase->offering?->default_session_minutes ?? 0);

        if ($offeringMinutes >= 30) {
            return $offeringMinutes;
        }

        return (int) config('studylikepro.services.default_session_minutes');
    }

    private function assertLessonPlanConstraints(ServicePurchase $purchase, int $durationMinutes): void
    {
        if (! $purchase->isActive()) {
            throw ValidationException::withMessages([
                'purchase' => __('This plan is no longer active.'),
            ]);
        }

        if ($purchase->expires_at !== null && $purchase->expires_at->isPast()) {
            throw ValidationException::withMessages([
                'purchase' => __('This plan has expired.'),
            ]);
        }

        $allowed = array_map('intval', (array) config('studylikepro.lesson_durations'));

        if (! in_array($durationMinutes, $allowed, true)) {
            throw ValidationException::withMessages([
                'duration_minutes' => __('Sessions must be one of: :durations minutes.', [
                    'durations' => implode(', ', $allowed),
                ]),
            ]);
        }

        if ($durationMinutes > $purchase->remainingMinutes()) {
            throw ValidationException::withMessages([
                'duration_minutes' => __('This session is longer than the time left on your plan (:minutes minutes).', [
                    'minutes' => $purchase->remainingMinutes(),
                ]),
            ]);
        }

        $sessionsRemaining = $purchase->sessionsRemaining();

        if ($sessionsRemaining !== null && $sessionsRemaining <= 0) {
            throw ValidationException::withMessages([
                'duration_minutes' => __('This plan has reached its session limit.'),
            ]);
        }
    }
}
