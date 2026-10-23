<?php

namespace App\Support;

use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\Grade;
use App\Models\ServiceOffering;
use App\Models\Subject;
use App\Models\TeacherProfile;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The JSON island behind the public teacher profile: resources/js/public
 * renders it inside the landing page's shell, so a visitor who clicks a
 * teacher never leaves the public navigation.
 *
 * Blade keeps ownership of every figure and label — the grades this teacher
 * actually takes, the rate per grade, the open slots in the viewer's timezone
 * and the one-line availability status — and hands them over as plain strings.
 */
final class PublicTeacherPageConfig
{
    /**
     * @param  array{gradesById?: Collection<int, Grade>, availability?: array<int, array<string, mixed>>, windows?: array<int, array{start: CarbonImmutable, end: CarbonImmutable}>, reviews?: Collection<int, mixed>, breakdown?: array<int, int>, slotLimit?: int, offerings?: Collection<int, ServiceOffering>}  $payload
     */
    public static function build(TeacherProfile $teacher, array $payload): array
    {
        $money = static fn (int $minor) => platform_settings()->formatMinor($minor);
        $copy = self::copy();

        $gradesById = $payload['gradesById'] ?? collect();
        $availability = self::availability(
            $teacher,
            $payload['availability'] ?? [],
            $payload['windows'] ?? [],
            self::viewerTimezone(),
            $copy,
            (int) ($payload['slotLimit'] ?? 0),
        );

        return [
            'appName' => config('app.name', 'Studylikepro'),
            'authenticated' => auth()->check(),
            'teacher' => self::teacher($teacher, $money, $copy),
            'services' => self::services($payload['offerings'] ?? collect(), $copy),
            'grades' => self::grades($teacher, $gradesById, $money),
            'availability' => $availability,
            'reviews' => self::reviews($teacher, $payload['reviews'] ?? collect(), $payload['breakdown'] ?? []),
            'booking' => self::booking($teacher, $money, $availability, $copy),
            'breadcrumbs' => [
                ['label' => __('Home'), 'url' => route('home')],
                ['label' => __('Teachers'), 'url' => route('teachers.index')],
                ['label' => $teacher->user->name, 'url' => null],
            ],
            'copy' => $copy,
            'urls' => self::urls(),
            'support' => self::support(),
            'images' => self::images(),
        ];
    }

    /**
     * The timezone every time on the page is converted to: the viewer's own
     * when they have one, Sri Lanka time for anonymous visitors.
     */
    private static function viewerTimezone(): string
    {
        return auth()->user()?->studentProfile?->timezone
            ?? auth()->user()?->teacherProfile?->timezone
            ?? config('studylikepro.default_display_timezone');
    }

    /**
     * @param  callable(int): string  $money
     * @return array<string, mixed>
     */
    private static function teacher(TeacherProfile $teacher, callable $money, array $copy): array
    {
        $ratingCount = (int) $teacher->rating_count;

        return [
            'name' => $teacher->user->name,
            'initials' => (string) Str::of($teacher->user->name)->substr(0, 2),
            'avatarUrl' => $teacher->user->avatarUrl(),
            'headline' => $teacher->headline,
            'bio' => $teacher->bio,
            'verifiedLabel' => $copy['verified'],
            'rating' => $ratingCount > 0 ? number_format((float) $teacher->rating_avg, 1) : null,
            'ratingCount' => $ratingCount,
            'reviewsLabel' => trans_choice(':count review|:count reviews', $ratingCount),
            'lessonsTaught' => $teacher->lessons_completed_count.' '.__('lessons taught'),
            'experience' => $teacher->experience_years.' '.__('yrs experience'),
            'rateChip' => $money($teacher->startingRateMinor()).$copy['perHourShort'],
            'facts' => [
                ['label' => __('Education'), 'value' => $teacher->education],
                ['label' => __('Languages'), 'value' => implode(', ', $teacher->languages ?? [])],
                ['label' => __('Lesson length'), 'value' => $teacher->lessonDuration().' '.__('minutes')],
            ],
        ];
    }

    /**
     * One badge per service category this teacher sells: a category appears
     * only when it has at least one active offering.
     *
     * @param  Collection<int, ServiceOffering>  $offerings
     * @return array<string, mixed>
     */
    private static function services(Collection $offerings, array $copy): array
    {
        $activeTypes = $offerings
            ->map(fn (ServiceOffering $offering) => $offering->service_type->value)
            ->unique();

        $badges = collect(ServiceType::cases())
            ->filter(fn (ServiceType $type) => $activeTypes->contains($type->value))
            ->map(fn (ServiceType $type) => [
                'key' => $type->value,
                'label' => $type->label(),
                'icon' => match ($type) {
                    ServiceType::Lesson => 'graduation',
                    ServiceType::PaperMarking => 'clipboard',
                    ServiceType::PaperDiscussion => 'chat',
                    ServiceType::Qna => 'sparkles',
                },
            ])
            ->values()
            ->all();

        return [
            'title' => $copy['servicesTitle'],
            'badges' => $badges,
        ];
    }

    /**
     * Only the grades this teacher actually teaches, grouped by level, each
     * with the rate a student would pay for it — a subject with no grade list
     * covers every grade of its level.
     *
     * @param  Collection<int, Grade>  $gradesById
     * @param  callable(int): string  $money
     * @return array<string, mixed>
     */
    private static function grades(TeacherProfile $teacher, Collection $gradesById, callable $money): array
    {
        $grades = [];

        foreach ($teacher->subjects as $subject) {
            foreach (self::gradesForSubject($teacher, $subject, $gradesById) as $grade) {
                $rate = $teacher->effectiveRateFor($subject, (int) $grade->id);
                $entry = $grades[$grade->id] ?? [
                    'grade' => $grade,
                    'level' => $subject->educationLevel,
                    'rate_minor' => $rate,
                    'subjects' => [],
                ];

                $entry['rate_minor'] = min($entry['rate_minor'], $rate);
                $entry['subjects'][$subject->id] = trim(($subject->icon ?? '').' '.$subject->name);
                $grades[$grade->id] = $entry;
            }
        }

        $groups = collect($grades)
            ->sortBy(fn (array $entry) => sprintf(
                '%04d-%04d',
                $entry['level']?->sort_order ?? 0,
                $entry['grade']->sort_order ?? 0,
            ))
            ->groupBy(fn (array $entry) => $entry['level']?->name ?? __('Other'))
            ->map(fn (Collection $entries, string $level) => [
                'level' => $level,
                'range' => $entries->first()['level']?->grade_min !== null
                    ? $entries->first()['level']?->gradeRangeLabel()
                    : null,
                'grades' => $entries->map(fn (array $entry) => [
                    'id' => $entry['grade']->id,
                    'label' => $entry['grade']->label,
                    'chip' => $entry['grade']->label.' · '.$money($entry['rate_minor']).'/hr',
                    'subjects' => array_values($entry['subjects']),
                ])->values()->all(),
            ])
            ->values()
            ->all();

        return [
            'summary' => $teacher->gradeScopeLabel($gradesById),
            'count' => count($grades),
            'groups' => $groups,
        ];
    }

    /**
     * @param  Collection<int, Grade>  $gradesById
     * @return Collection<int, Grade>
     */
    private static function gradesForSubject(TeacherProfile $teacher, Subject $subject, Collection $gradesById): Collection
    {
        $ids = $teacher->gradeIdsFor($subject);

        if ($ids === []) {
            return ($subject->educationLevel?->grades ?? collect())->sortBy('sort_order')->values();
        }

        $resolved = $gradesById->only($ids)->values();

        return $resolved->count() === count($ids)
            ? $resolved->sortBy('sort_order')->values()
            : Grade::query()->whereIn('id', $ids)->ordered()->get();
    }

    /**
     * The open slots grouped by day in the viewer's timezone, plus the wider
     * windows and the status line the page can keep ticking without a reload.
     *
     * @param  array<int, array<string, mixed>>  $slots
     * @param  array<int, array{start: CarbonImmutable, end: CarbonImmutable}>  $windows
     * @return array<string, mixed>
     */
    private static function availability(TeacherProfile $teacher, array $slots, array $windows, string $timezone, array $copy, int $slotLimit = 0): array
    {
        $now = CarbonImmutable::now($timezone);
        $today = $now->toDateString();
        $tomorrow = $now->addDay()->toDateString();

        $byDate = [];

        foreach ($slots as $slot) {
            $startsAt = $slot['starts_at']->setTimezone($timezone);
            $byDate[$startsAt->toDateString()][] = [
                'iso' => $startsAt->toIso8601String(),
                'time' => $startsAt->format(TimeFormat::TIME),
                'endTime' => $slot['ends_at']->setTimezone($timezone)->format(TimeFormat::TIME),
            ];
        }

        $days = [];

        foreach (array_slice($byDate, 0, 7, true) as $date => $daySlots) {
            $day = CarbonImmutable::parse($date, $timezone);

            $days[] = [
                'date' => $date,
                'weekday' => $day->format('D'),
                'dayNumber' => $day->format('j'),
                'month' => $day->format('M'),
                'fullLabel' => $day->format('l, j F'),
                'shortLabel' => self::dayLabel($date, $today, $tomorrow),
                'isToday' => $date === $today,
                'isTomorrow' => $date === $tomorrow,
                'slotCount' => count($daySlots),
                'slotCountLabel' => trans_choice(':count slot|:count slots', count($daySlots)),
                'slots' => $daySlots,
            ];
        }

        $windowCards = array_map(fn (array $window) => [
            'start' => $window['start']->toIso8601String(),
            'end' => $window['end']->toIso8601String(),
            'dayLabel' => self::dayLabel($window['start']->setTimezone($timezone)->toDateString(), $today, $tomorrow),
            'startTime' => $window['start']->setTimezone($timezone)->format(TimeFormat::TIME),
            'endTime' => $window['end']->setTimezone($timezone)->format(TimeFormat::TIME),
        ], $windows);

        $slotCount = count($slots);
        $slotCountCapped = $slotLimit > 0 && $slotCount >= $slotLimit;

        return [
            'timezone' => $timezone,
            'timezoneLabel' => __('Your time (:timezone)', ['timezone' => $timezone]),
            'timezoneNote' => __('All times in your timezone (:timezone).', ['timezone' => $timezone]),
            'empty' => $days === [],
            'slotCount' => $slotCount,
            // The slot list is capped, so the count is a floor once the cap is hit.
            'slotCountCapped' => $slotCountCapped,
            'days' => $days,
            'windows' => $windowCards,
            'status' => self::status(
                $windowCards,
                $days,
                $slotCountCapped
                    ? __(':count+ open slots', ['count' => $slotCount])
                    : trans_choice(':count open slot|:count open slots', $slotCount),
                $copy,
            ),
            'lessonMinutes' => $teacher->lessonDuration(),
        ];
    }

    /**
     * The one-line availability answer: free right now, free later today,
     * next free slot, or nothing in the next two weeks.
     *
     * @param  array<int, array<string, string>>  $windows
     * @param  array<int, array<string, mixed>>  $days
     * @param  array<string, string>  $copy
     * @return array{state: string, label: string, detail: string|null}
     */
    private static function status(array $windows, array $days, string $slotCountLabel, array $copy): array
    {
        if ($days === []) {
            return ['state' => 'none', 'label' => $copy['notAvailableNow'], 'detail' => $copy['noSlots']];
        }

        $now = CarbonImmutable::now('UTC');

        foreach ($windows as $window) {
            $startsAt = CarbonImmutable::parse($window['start']);
            $endsAt = CarbonImmutable::parse($window['end']);

            if ($startsAt->lessThanOrEqualTo($now) && $now->lessThan($endsAt)) {
                return [
                    'state' => 'now',
                    'label' => $copy['availableNow'],
                    'detail' => str_replace(':time', $window['endTime'], $copy['freeUntil']),
                ];
            }
        }

        foreach ($windows as $window) {
            $startsAt = CarbonImmutable::parse($window['start']);

            if ($startsAt->lessThanOrEqualTo($now)) {
                continue;
            }

            $minutes = (int) ceil(($startsAt->timestamp - $now->timestamp) / 60);

            $label = match (true) {
                $window['dayLabel'] === $copy['today'] && $minutes <= 90 => str_replace(':minutes', (string) $minutes, $copy['availableIn']),
                $window['dayLabel'] === $copy['today'] => str_replace(':time', $window['startTime'], $copy['availableToday']),
                $window['dayLabel'] === $copy['tomorrow'] => str_replace(':time', $window['startTime'], $copy['availableTomorrow']),
                default => strtr($copy['nextAvailable'], [':day' => $window['dayLabel'], ':time' => $window['startTime']]),
            };

            return [
                'state' => 'soon',
                'label' => $label,
                'detail' => $window['startTime'].'–'.$window['endTime'],
            ];
        }

        $firstDay = $days[0];

        return [
            'state' => 'later',
            'label' => strtr($copy['nextOpenSlot'], [':day' => $firstDay['shortLabel'], ':time' => $firstDay['slots'][0]['time']]),
            'detail' => $slotCountLabel,
        ];
    }

    private static function dayLabel(string $date, string $today, string $tomorrow): string
    {
        return match ($date) {
            $today => __('Today'),
            $tomorrow => __('Tomorrow'),
            default => CarbonImmutable::parse($date)->format('D j M'),
        };
    }

    /**
     * @param  Collection<int, mixed>  $reviews
     * @param  array<int, int>  $breakdown
     * @return array<string, mixed>
     */
    private static function reviews(TeacherProfile $teacher, Collection $reviews, array $breakdown): array
    {
        $ratingCount = (int) $teacher->rating_count;
        $total = array_sum($breakdown);

        return [
            'count' => $ratingCount,
            'rating' => $ratingCount > 0 ? number_format((float) $teacher->rating_avg, 1) : null,
            'summaryLabel' => trans_choice(':count review|:count reviews', $ratingCount),
            'breakdown' => collect($breakdown)->map(fn (int $count, int $star) => [
                'label' => $star.' ★',
                'count' => $count,
                'percent' => $total > 0 ? (int) round($count / $total * 100) : 0,
            ])->values()->all(),
            'items' => $reviews->map(fn ($review) => [
                'id' => $review->id,
                'reviewer' => $review->reviewerName(),
                'rating' => (int) $review->rating,
                'stars' => [
                    'filled' => str_repeat('★', (int) $review->rating),
                    'empty' => str_repeat('★', 5 - (int) $review->rating),
                ],
                'comment' => $review->comment,
                'meta' => collect([
                    $review->booking?->lesson?->name ?? $review->booking?->subject?->name ?? __('Lesson'),
                    $review->created_at->format('M Y'),
                    $review->wasEdited() ? __('edited') : null,
                ])->filter()->implode(' · '),
            ])->values()->all(),
            'showingLabel' => $ratingCount > $reviews->count()
                ? __('Showing the :count most recent reviews.', ['count' => $reviews->count()])
                : null,
        ];
    }

    /**
     * @param  callable(int): string  $money
     * @param  array<string, mixed>  $availability
     * @return array<string, mixed>
     */
    private static function booking(TeacherProfile $teacher, callable $money, array $availability, array $copy): array
    {
        $user = auth()->user();
        $canBook = $user !== null && $user->can('create', [Booking::class, $teacher]);

        $action = match (true) {
            $canBook => ['label' => __('Book a lesson'), 'url' => route('student.bookings.create', $teacher)],
            $user !== null => ['label' => __('Go to dashboard'), 'url' => route('dashboard')],
            default => ['label' => __('Log in to book'), 'url' => route('login')],
        };

        return [
            'canBook' => $canBook,
            'startingAt' => __('Starting at'),
            'rate' => $money($teacher->startingRateMinor()),
            'perHour' => $copy['perHourShort'],
            'lessonLength' => __(':minutes-minute live 1-on-1 lesson.', ['minutes' => $teacher->lessonDuration()]),
            'timezoneLabel' => $availability['timezoneLabel'],
            'slotsLabel' => $availability['empty']
                ? $copy['noSlots']
                : ($availability['slotCountCapped']
                    ? __(':count+ open slots in the next two weeks', ['count' => $availability['slotCount']])
                    : trans_choice(':count open slot in the next two weeks|:count open slots in the next two weeks', $availability['slotCount'])),
            'action' => $action,
            'secondary' => $user === null
                ? ['label' => __('Create a student account'), 'url' => route('register', ['role' => 'student'])]
                : null,
            'trust' => [
                ['icon' => 'shield-check', 'label' => __('Every teacher is document-checked')],
                ['icon' => 'calendar', 'label' => __('Free cancellation up to :hours hours before the lesson', ['hours' => platform_settings()->int('student_cancel_window_hours')])],
                ['icon' => 'wallet', 'label' => __('Secure card payment in :currency', ['currency' => config('studylikepro.currency')])],
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function copy(): array
    {
        return [
            'verified' => __('Verified'),
            'live' => __('Live'),
            'liveNote' => __('Updates live'),
            'aboutTitle' => __('About'),
            'servicesTitle' => __('Services offered'),
            'gradesTitle' => __('Grades taught'),
            'gradesIntro' => __('Only the grades this teacher takes are listed, each with the rate you would pay.'),
            'gradesEmpty' => __('This teacher is still setting up their grades.'),
            'subjectsLabel' => __('Subjects'),
            'availabilityTitle' => __('Availability'),
            'availabilityIntro' => __('Open lesson slots for the next two weeks. Pick a day to see the times.'),
            'noSlots' => __('No open slots in the next two weeks — check back soon.'),
            'slotNote' => __('Slots update live as lessons are booked.'),
            'availableNow' => __('Available now'),
            'notAvailableNow' => __('Not available right now'),
            'freeUntil' => __('Free until :time'),
            'availableIn' => __('Available in :minutes min'),
            'availableToday' => __('Available today at :time'),
            'availableTomorrow' => __('Available tomorrow at :time'),
            'nextAvailable' => __('Next available :day at :time'),
            'nextOpenSlot' => __('Next open slot :day at :time'),
            'openSlots' => __(':count open slot|:count open slots'),
            'openSlotsCapped' => __(':count+ open slots'),
            'today' => __('Today'),
            'tomorrow' => __('Tomorrow'),
            'newTeacher' => __('New teacher'),
            'reviewsTitle' => __('Student reviews'),
            'noReviews' => __('No reviews yet — this teacher is new to Studylikepro.'),
            'outOfFive' => __('out of 5'),
            'findTeacher' => __('Find a teacher'),
            'perHourShort' => __('/hr'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function urls(): array
    {
        return [
            'home' => route('home'),
            'subjects' => route('catalog.subjects.index'),
            'teachers' => route('teachers.index'),
            'contact' => route('legal.contact'),
            'login' => route('login'),
            'registerStudent' => route('register', ['role' => 'student']),
            'dashboard' => route('dashboard'),
            'privacy' => route('legal.privacy'),
            'terms' => route('legal.terms'),
            'refunds' => route('legal.refunds'),
        ];
    }

    /**
     * @return array<string, string|null>
     */
    private static function support(): array
    {
        return [
            'email' => config('studylikepro.support.email'),
            'phone' => config('studylikepro.support.phone'),
            'hours' => config('studylikepro.support.hours'),
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function images(): array
    {
        return [
            'logo' => asset('images/brand/logo-mark.png'),
        ];
    }
}
