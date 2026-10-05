<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\TeacherAvailabilitySlot;
use App\Models\TeacherProfile;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Turns a teacher's weekly ranges (stored in their own timezone) into concrete
 * bookable slots in UTC, excluding past times, time off, and taken ranges.
 */
class SlotService
{
    public const MAX_SLOTS = 200;

    /**
     * @param  array<int, array{0: CarbonInterface, 1: CarbonInterface}>  $excluded  UTC ranges already booked
     * @return list<array{starts_at: CarbonImmutable, ends_at: CarbonImmutable, local_date: string, local_time: string}>
     */
    public function openSlots(
        TeacherProfile $teacher,
        CarbonInterface $from,
        CarbonInterface $until,
        ?int $durationMinutes = null,
        array $excluded = [],
        int $limit = self::MAX_SLOTS,
    ): array {
        $duration = $durationMinutes ?? $teacher->lessonDuration();
        $timezone = $teacher->timezone ?: config('app.timezone');

        $fromUtc = CarbonImmutable::instance($from)->utc();
        $untilUtc = CarbonImmutable::instance($until)->utc();
        $now = CarbonImmutable::now('UTC');

        $weekly = $this->weeklyRanges($teacher);
        $timeOff = $teacher->relationLoaded('timeOff')
            ? $teacher->timeOff
            : $teacher->timeOff()->get();

        $excluded = array_map(
            fn (array $range) => [
                CarbonImmutable::instance($range[0])->utc(),
                CarbonImmutable::instance($range[1])->utc(),
            ],
            $excluded
        );

        $slots = [];
        $cursor = $fromUtc->setTimezone($timezone)->startOfDay();
        $lastDay = $untilUtc->setTimezone($timezone)->endOfDay();

        while ($cursor->lessThanOrEqualTo($lastDay)) {
            if ($timeOff->contains(fn ($entry) => $entry->coversDate($cursor))) {
                $cursor = $cursor->addDay();

                continue;
            }

            $seen = [];

            foreach ($weekly->get($cursor->dayOfWeek, collect()) as $range) {
                for (
                    $minute = $range->start_minute;
                    $minute + $duration <= $range->end_minute;
                    $minute += $duration
                ) {
                    $startsAt = $this->localMinuteToUtc($cursor, $minute, $timezone);

                    // Nonexistent local times shift forward across DST; skip duplicates.
                    if (isset($seen[$startsAt->timestamp])) {
                        continue;
                    }

                    $seen[$startsAt->timestamp] = true;
                    $endsAt = $startsAt->addMinutes($duration);

                    if ($startsAt->lessThanOrEqualTo($now) || $endsAt->greaterThan($untilUtc)) {
                        continue;
                    }

                    if ($startsAt->lessThan($fromUtc)) {
                        continue;
                    }

                    if ($this->overlaps($startsAt, $endsAt, $excluded)) {
                        continue;
                    }

                    $slots[] = [
                        'starts_at' => $startsAt,
                        'ends_at' => $endsAt,
                        'local_date' => $cursor->toDateString(),
                        'local_time' => sprintf('%02d:%02d', intdiv($minute, 60), $minute % 60),
                    ];

                    if (count($slots) >= $limit) {
                        return $slots;
                    }
                }
            }

            $cursor = $cursor->addDay();
        }

        return $slots;
    }

    /**
     * The next open slots from now, in chronological order.
     *
     * @return list<array{starts_at: CarbonImmutable, ends_at: CarbonImmutable, local_date: string, local_time: string}>
     */
    public function upcoming(TeacherProfile $teacher, int $days = 7, int $limit = 24, bool $excludeBookings = false): array
    {
        $from = CarbonImmutable::now();
        $until = $from->addDays($days);

        return $this->openSlots(
            $teacher,
            $from,
            $until,
            null,
            $excludeBookings ? $this->blockedRanges($teacher, $from, $until) : [],
            $limit,
        );
    }

    /**
     * The next open slots from now, grouped by the teacher's local date.
     *
     * @return array<string, list<array{starts_at: CarbonImmutable, ends_at: CarbonImmutable, local_date: string, local_time: string}>>
     */
    public function upcomingByDate(TeacherProfile $teacher, int $days = 7, int $limit = 24, bool $excludeBookings = false): array
    {
        $grouped = [];

        foreach ($this->upcoming($teacher, $days, $limit, $excludeBookings) as $slot) {
            $grouped[$slot['local_date']][] = $slot;
        }

        return $grouped;
    }

    /**
     * Whether the teacher's weekly schedule covers the given instant.
     */
    public function isAvailableAt(TeacherProfile $teacher, CarbonInterface $start, ?int $durationMinutes = null): bool
    {
        $duration = $durationMinutes ?? $teacher->lessonDuration();
        $timezone = $teacher->timezone ?: config('app.timezone');

        $localStart = CarbonImmutable::instance($start)->utc()->setTimezone($timezone);
        $startMinute = $localStart->hour * 60 + $localStart->minute;

        if ($teacher->timeOff()->get()->contains(fn ($entry) => $entry->coversDate($localStart))) {
            return false;
        }

        return $this->weeklyRanges($teacher)
            ->get($localStart->dayOfWeek, collect())
            ->contains(fn ($range) => $range->start_minute <= $startMinute
                && $range->end_minute >= $startMinute + $duration);
    }

    /**
     * Ranges already taken by live holds, confirmed and in-progress lessons.
     *
     * @return list<array{0: CarbonInterface, 1: CarbonInterface}>
     */
    public function blockedRanges(TeacherProfile $teacher, CarbonInterface $from, CarbonInterface $to): array
    {
        return Booking::query()
            ->where('teacher_profile_id', $teacher->id)
            ->blockingSlot()
            ->overlapping($from, $to)
            ->get()
            ->map(fn (Booking $booking) => [$booking->starts_at, $booking->ends_at])
            ->all();
    }

    /**
     * @return Collection<int, Collection<int, TeacherAvailabilitySlot>>
     */
    private function weeklyRanges(TeacherProfile $teacher): Collection
    {
        $ranges = $teacher->relationLoaded('availabilitySlots')
            ? $teacher->availabilitySlots
            : $teacher->availabilitySlots()->get();

        return $ranges
            ->groupBy('day_of_week')
            ->map(fn (Collection $group) => $group->sortBy('start_minute')->values());
    }

    private function localMinuteToUtc(CarbonImmutable $date, int $minute, string $timezone): CarbonImmutable
    {
        return $date
            ->setTime(intdiv($minute, 60), $minute % 60)
            ->setTimezone('UTC');
    }

    /**
     * @param  array<int, array{0: CarbonImmutable, 1: CarbonImmutable}>  $excluded
     */
    private function overlaps(CarbonImmutable $startsAt, CarbonImmutable $endsAt, array $excluded): bool
    {
        foreach ($excluded as [$rangeStart, $rangeEnd]) {
            if ($startsAt->lessThan($rangeEnd) && $endsAt->greaterThan($rangeStart)) {
                return true;
            }
        }

        return false;
    }
}
