<?php

namespace App\Services;

use App\Models\RequestResponse;
use App\Models\Subject;
use App\Models\TeacherProfile;
use App\Models\TutoringRequest;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
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
     * Approved teachers who teach the request's lesson, cover its grade, and
     * have a bookable slot inside at least one preferred window.
     *
     * @return Collection<int, TeacherProfile>
     */
    public function teachersFor(TutoringRequest $request): Collection
    {
        if (! $request->isMatchable()) {
            return collect();
        }

        $subjectId = $request->subject_id ?: $request->lesson?->subject_id;

        return TeacherProfile::query()
            ->approved()
            ->whereHas('lessons', fn ($query) => $query->where('lessons.id', $request->lesson_id))
            ->when(
                $subjectId !== null || $request->grade_id !== null,
                fn ($query) => $query->whereHas('subjects', fn (Builder $relation) => $this->scopeToRequestGrade($relation, $request, $subjectId)),
            )
            ->with(['user', 'availabilitySlots', 'timeOff'])
            ->get()
            ->filter(fn (TeacherProfile $teacher) => $this->slotsFor($teacher, $request, 1) !== [])
            ->values();
    }

    /**
     * Open requests a teacher can respond to (lesson + grade + availability).
     */
    public function inboxFor(TeacherProfile $teacher, int $page = 1, int $perPage = 10): LengthAwarePaginator
    {
        $lessonIds = $teacher->lessons()->pluck('lessons.id');

        $gradeScopes = $teacher->subjects()->get()->mapWithKeys(
            fn (Subject $subject) => [
                $subject->id => array_map('intval', (array) ($subject->pivot->grade_levels ?? [])),
            ]
        );

        $matches = TutoringRequest::query()
            ->matchable()
            ->whereIn('lesson_id', $lessonIds)
            ->with(['subject', 'lesson', 'student', 'attachments'])
            ->withExists(['responses as responded' => fn ($query) => $query->where('teacher_profile_id', $teacher->id)])
            ->latest()
            ->get()
            ->filter(fn (TutoringRequest $request) => $this->coversGrade($gradeScopes, $request))
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

    /**
     * Constrain a teacher-subjects relation to the request's subject and grade
     * scope (grade ids live as a JSON array on the pivot).
     *
     * @param  Builder<Subject>  $relation
     * @return Builder<Subject>
     */
    private function scopeToRequestGrade(Builder $relation, TutoringRequest $request, ?int $subjectId): Builder
    {
        if ($subjectId !== null) {
            $relation->where('subjects.id', $subjectId);
        }

        if ($request->grade_id !== null) {
            // A quoted LIKE keeps the JSON match exact ("6" must not match "16").
            $relation->where('teacher_subjects.grade_levels', 'like', '%"'.$request->grade_id.'"%');
        }

        return $relation;
    }

    /**
     * Whether the teacher's per-subject grade scopes cover the request's grade.
     * Requests without a grade (legacy) stay visible.
     *
     * @param  Collection<int|string, list<int>>  $gradeScopes  subject id → grade ids
     */
    private function coversGrade(Collection $gradeScopes, TutoringRequest $request): bool
    {
        if ($request->grade_id === null) {
            return true;
        }

        $subjectId = $request->subject_id ?? $request->lesson?->subject_id;

        return in_array((int) $request->grade_id, $gradeScopes->get($subjectId, []), true);
    }
}
