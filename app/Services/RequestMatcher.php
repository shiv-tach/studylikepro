<?php

namespace App\Services;

use App\Models\RequestResponse;
use App\Models\TeacherProfile;
use App\Models\TutoringRequest;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Connects tutoring requests with the verified teachers who can actually take them.
 */
class RequestMatcher
{
    public function __construct(private readonly SlotService $slots) {}

    /**
     * Approved teachers who teach the request's topic and have a bookable slot
     * inside at least one preferred window.
     *
     * @return Collection<int, TeacherProfile>
     */
    public function teachersFor(TutoringRequest $request): Collection
    {
        if (! $request->isMatchable()) {
            return collect();
        }

        return TeacherProfile::query()
            ->approved()
            ->whereHas('topics', fn ($query) => $query->where('topics.id', $request->topic_id))
            ->with(['user', 'availabilitySlots', 'timeOff'])
            ->get()
            ->filter(fn (TeacherProfile $teacher) => $this->slotsFor($teacher, $request, 1) !== [])
            ->values();
    }

    /**
     * Open requests a teacher can respond to (topic + availability overlap).
     */
    public function inboxFor(TeacherProfile $teacher, int $page = 1, int $perPage = 10): LengthAwarePaginator
    {
        $topicIds = $teacher->topics()->pluck('topics.id');

        $matches = TutoringRequest::query()
            ->matchable()
            ->whereIn('topic_id', $topicIds)
            ->with(['subject', 'topic', 'student', 'attachments'])
            ->withExists(['responses as responded' => fn ($query) => $query->where('teacher_profile_id', $teacher->id)])
            ->latest()
            ->get()
            ->filter(fn (TutoringRequest $request) => $this->slotsFor($teacher, $request, 1) !== [])
            ->values();

        return new LengthAwarePaginator(
            $matches->forPage($page, $perPage)->values(),
            $matches->count(),
            $perPage,
            $page,
            ['path' => route('teacher.requests.index')],
        );
    }

    /**
     * Cached count used by the teacher navigation badge.
     */
    public function openMatchCount(TeacherProfile $teacher): int
    {
        return Cache::remember(
            "teacher:{$teacher->id}:open-request-matches",
            now()->addMinutes(5),
            fn () => $this->inboxFor($teacher)->total(),
        );
    }

    /**
     * Bookable slots inside the request's preferred windows, in chronological order.
     *
     * @return list<array{starts_at: CarbonImmutable, ends_at: CarbonImmutable, local_date: string, local_time: string}>
     */
    public function slotsFor(TeacherProfile $teacher, TutoringRequest $request, int $limit = 12): array
    {
        $slots = [];

        foreach ($request->windows() as [$start, $end]) {
            $found = $this->slots->openSlots(
                $teacher,
                $start,
                $end,
                null,
                $this->slots->blockedRanges($teacher, $start, $end),
                $limit,
            );

            foreach ($found as $slot) {
                $slots[$slot['starts_at']->toIso8601String()] = $slot;
            }

            if (count($slots) >= $limit) {
                break;
            }
        }

        ksort($slots);

        return array_slice(array_values($slots), 0, $limit);
    }

    /**
     * Whether a specific proposed time is still open for this teacher.
     */
    public function isSlotOpen(TeacherProfile $teacher, CarbonInterface $startsAt, CarbonInterface $endsAt): bool
    {
        if ($startsAt->lessThanOrEqualTo(now())) {
            return false;
        }

        if (! $this->slots->isAvailableAt($teacher, $startsAt, (int) $startsAt->diffInMinutes($endsAt))) {
            return false;
        }

        return $this->slots->blockedRanges($teacher, $startsAt, $endsAt) === [];
    }

    /**
     * Responses that are waiting on the student, newest first.
     *
     * @return Collection<int, RequestResponse>
     */
    public function pendingResponsesFor(TutoringRequest $request): Collection
    {
        return $request->responses()
            ->where('status', 'pending')
            ->with(['teacherProfile.user', 'teacherProfile.subjects'])
            ->get();
    }
}
