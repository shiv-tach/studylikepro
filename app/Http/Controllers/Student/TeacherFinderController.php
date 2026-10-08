<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\StudentTeacherSearchRequest;
use App\Models\TeacherProfile;
use App\Services\CatalogService;
use App\Services\SlotService;
use App\Services\TeacherSearch;
use Carbon\CarbonImmutable;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\View\View;

/**
 * The student's own teacher discovery page: every search is scoped to the
 * grade from the student's learning profile, so the only filters left are
 * subject, language and when they can take the lesson.
 */
class TeacherFinderController extends Controller
{
    /** How far ahead a card's "next available" preview looks. */
    private const NEXT_SLOT_DAYS = 7;

    public function __construct(
        private readonly TeacherSearch $search,
        private readonly SlotService $slots,
        private readonly CatalogService $catalog,
    ) {}

    public function __invoke(StudentTeacherSearchRequest $request): View
    {
        $student = $request->user();
        $grade = $student->studentProfile?->grade;

        $filters = $request->validated();
        $sort = $request->sort();

        $date = filled($filters['date'] ?? null) ? $filters['date'] : null;
        $timeFrom = $filters['time_from'] ?? null;
        $timeTo = $filters['time_to'] ?? null;

        unset($filters['date'], $filters['sort']);

        // The search service filters on the weekly slot, so a concrete date
        // becomes its weekday plus the same time window.
        if ($date !== null) {
            $filters['weekday'] = CarbonImmutable::parse($date)->dayOfWeek;
        }

        // The profile grade is the one filter the student cannot change.
        if ($grade !== null) {
            $filters['grade'] = $grade->id;
        }

        if ($date !== null || $sort === 'available_soon') {
            [$teachers, $nextSlots] = $this->paginateByAvailability($filters, $date, $timeFrom, $timeTo, $sort);
        } else {
            $teachers = $this->search->paginate($filters);
            $nextSlots = $this->nextSlotsFor($teachers->getCollection());
        }

        return view('student.find-teacher', [
            'teachers' => $teachers,
            'nextSlots' => $nextSlots,
            'filters' => $request->validated(),
            'sort' => $sort,
            'grade' => $grade,
            'subjects' => $grade !== null ? $this->catalog->subjectsForGrade($grade) : $this->catalog->subjects(),
            'gradesById' => $this->catalog->levels()->flatMap(fn ($level) => $level->grades)->keyBy('id'),
            'viewerTz' => $student->studentProfile?->timezone ?? config('studylikepro.default_display_timezone'),
        ]);
    }

    /**
     * The whole matching set scored with a real bookable slot — on the chosen
     * date when one is picked, otherwise the next open slot from now. Slot
     * availability lives outside SQL (weekly ranges, time off, live holds), so
     * this path orders and paginates the collection in PHP.
     *
     * @param  array<string, mixed>  $filters
     * @return array{0: LengthAwarePaginator<int, TeacherProfile>, 1: array<int, array<string, mixed>|null>}
     */
    private function paginateByAvailability(array $filters, ?string $date, ?string $timeFrom, ?string $timeTo, string $sort): array
    {
        $rows = $this->search->query($filters)
            ->get()
            ->map(fn (TeacherProfile $teacher) => [
                'teacher' => $teacher,
                'slot' => $date !== null
                    ? $this->firstSlotOn($teacher, $date, $timeFrom, $timeTo)
                    : ($this->slots->upcoming($teacher, self::NEXT_SLOT_DAYS, 1, excludeBookings: true)[0] ?? null),
            ])
            ->when($date !== null, fn (Collection $rows) => $rows->filter(fn (array $row) => $row['slot'] !== null));

        if ($sort === 'available_soon') {
            // PHP_INT_MAX keeps teachers with no open slot in the next week at
            // the end; sortBy is stable, so the rating order breaks ties.
            $rows = $rows->sortBy(fn (array $row) => $row['slot'] !== null ? $row['slot']['starts_at']->getTimestamp() : PHP_INT_MAX);
        }

        $rows = $rows->values();
        $page = Paginator::resolveCurrentPage();
        $pageRows = $rows->forPage($page, TeacherSearch::PER_PAGE)->values();

        $teachers = new LengthAwarePaginator(
            $pageRows->pluck('teacher'),
            $rows->count(),
            TeacherSearch::PER_PAGE,
            $page,
        );

        return [
            $teachers->withQueryString(),
            $pageRows->mapWithKeys(fn (array $row) => [$row['teacher']->id => $row['slot']])->all(),
        ];
    }

    /**
     * The first open slot on a calendar date, read on the teacher's own clock
     * like the directory's weekday filter. A time window narrows it further.
     *
     * @return array{starts_at: CarbonImmutable, ends_at: CarbonImmutable, local_date: string, local_time: string}|null
     */
    private function firstSlotOn(TeacherProfile $teacher, string $date, ?string $timeFrom, ?string $timeTo): ?array
    {
        $day = CarbonImmutable::parse($date, $teacher->timezone ?: config('app.timezone'))->startOfDay();
        $endOfDay = $day->endOfDay();

        $slots = $this->slots->openSlots(
            $teacher,
            $day,
            $endOfDay,
            null,
            $this->slots->blockedRanges($teacher, $day, $endOfDay),
        );

        foreach ($slots as $slot) {
            if ($timeFrom !== null && $slot['local_time'] < $timeFrom) {
                continue;
            }

            if ($timeTo !== null && $slot['local_time'] >= $timeTo) {
                continue;
            }

            return $slot;
        }

        return null;
    }

    /**
     * @param  Collection<int, TeacherProfile>  $teachers
     * @return array<int, array<string, mixed>|null>
     */
    private function nextSlotsFor(Collection $teachers): array
    {
        return $teachers
            ->mapWithKeys(fn (TeacherProfile $teacher) => [
                $teacher->id => $this->slots->upcoming($teacher, self::NEXT_SLOT_DAYS, 1, excludeBookings: true)[0] ?? null,
            ])
            ->all();
    }
}
