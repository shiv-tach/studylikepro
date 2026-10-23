<?php

namespace App\Support;

use App\Enums\BookingStatus;
use App\Models\Booking;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * The JSON island behind /teacher/schedule.
 *
 * Blade still owns every label, timezone-formatted time, money value and route,
 * so the Vue app never formats a date or hardcodes a URL. The page is built
 * around two questions — what is next, and what needs the teacher now — so the
 * payload carries a ranked priority queue beside the plain agenda.
 *
 * The action flags mirror BookingPolicy and BookingTransitionService: the page
 * only offers a transition the controller would actually accept.
 */
final class TeacherScheduleConfig
{
    /**
     * @param  Collection<int, Booking>  $upcoming  Confirmed and in-progress lessons, soonest first.
     * @param  Collection<int, Booking>  $holds  Unpaid holds that have not lapsed.
     * @param  Collection<int, Booking>  $past  The most recent finished lessons.
     * @param  Collection<int, Booking>  $week  Confirmed lessons in the next seven days (dates only).
     */
    public static function build(
        string $timezone,
        string $preset,
        Collection $upcoming,
        Collection $holds,
        Collection $past,
        Collection $week,
    ): array {
        $earlyMinutes = (int) config('studylikepro.booking.teacher_start_early_minutes');

        $cards = $upcoming
            ->map(fn (Booking $booking) => self::card($booking, $timezone, $earlyMinutes))
            ->values();

        return [
            'appName' => config('app.name', 'Studylikepro'),
            'csrfToken' => csrf_token(),
            'timezone' => $timezone,
            'theme' => ['preset' => $preset],
            'flash' => self::flash(),
            'errors' => ['status' => session('errors')?->first('status') ?: null],
            'stats' => self::stats($week, $holds, $timezone),
            'next' => self::next($cards->all(), $upcoming),
            'priority' => self::priority($upcoming, $holds, $timezone, $earlyMinutes),
            'agenda' => self::agenda($upcoming, $timezone, $earlyMinutes),
            'holds' => $holds->map(fn (Booking $booking) => self::holdCard($booking, $timezone, $earlyMinutes))->values()->all(),
            'history' => $past->map(fn (Booking $booking) => self::historyRow($booking, $timezone))->values()->all(),
            'tabs' => [
                ['key' => 'upcoming', 'label' => __('Upcoming lessons'), 'count' => $cards->count()],
                ['key' => 'holds', 'label' => __('Awaiting payment'), 'count' => $holds->count()],
                ['key' => 'history', 'label' => __('Recent history'), 'count' => $past->count()],
            ],
            'copy' => self::copy($timezone),
            'urls' => [
                'schedule' => route('teacher.schedule.index'),
                'availability' => route('teacher.availability.index'),
                'availabilitySetup' => route('teacher.availability.setup'),
                'dashboard' => route('teacher.dashboard'),
                'messages' => route('messages.index'),
            ],
        ];
    }

    /**
     * The spotlight card: the live lesson, or the soonest one that has not
     * finished yet. Null when the teacher's next lessons are all in the past
     * (those surface in the priority queue instead).
     *
     * @param  list<array<string, mixed>>  $cards
     * @param  Collection<int, Booking>  $upcoming
     */
    private static function next(array $cards, Collection $upcoming): ?array
    {
        $index = $upcoming
            ->values()
            ->search(fn (Booking $booking) => $booking->status->isLive() && $booking->ends_at->isFuture());

        if ($index === false) {
            return null;
        }

        $card = $cards[$index] ?? null;

        if ($card === null) {
            return null;
        }

        $booking = $upcoming->values()[$index];

        return [
            ...$card,
            'eyebrow' => $booking->status === BookingStatus::InProgress
                ? __('Happening now')
                : __('Up next'),
            'isLive' => $booking->status === BookingStatus::InProgress,
            'startsInLabel' => $booking->starts_at->isFuture()
                ? __('Starts in')
                : __('Started'),
        ];
    }

    /**
     * Everything the teacher has to act on, most urgent first: close a finished
     * lesson, run a live one, join an open room, prepare for one starting within
     * the hour, or watch a hold that is about to lapse.
     *
     * @param  Collection<int, Booking>  $upcoming
     * @param  Collection<int, Booking>  $holds
     * @return list<array<string, mixed>>
     */
    private static function priority(Collection $upcoming, Collection $holds, string $timezone, int $earlyMinutes): array
    {
        $items = [];

        foreach ($upcoming as $booking) {
            $card = self::card($booking, $timezone, $earlyMinutes);
            $finished = $booking->ends_at->isPast();
            $live = $booking->status === BookingStatus::InProgress;

            if ($finished) {
                $items[] = self::priorityItem(10, 'needs_outcome', $card, [
                    'eyebrow' => __('Needs an outcome'),
                    'body' => __('The lesson time has passed. Close it so the student gets their receipt and your earnings settle.'),
                    'tone' => $live ? 'rose' : 'amber',
                ]);

                continue;
            }

            if ($live) {
                $items[] = self::priorityItem(30, 'in_progress', $card, [
                    'eyebrow' => __('In progress'),
                    'body' => __('The classroom is open. Mark the lesson complete as soon as you finish.'),
                    'tone' => 'primary',
                ]);

                continue;
            }

            if ($booking->canJoinNow()) {
                $items[] = self::priorityItem(40, 'join_now', $card, [
                    'eyebrow' => __('Classroom open'),
                    'body' => __('Your student can join now — open the room and start the lesson.'),
                    'tone' => 'emerald',
                ]);

                continue;
            }

            if ($booking->starts_at->lessThanOrEqualTo(now()->addHour())) {
                $items[] = self::priorityItem(50, 'starting_soon', $card, [
                    'eyebrow' => self::relative($booking->starts_at),
                    'body' => __('The classroom opens a few minutes early, so you can settle in before the student arrives.'),
                    'tone' => 'sky',
                ]);
            }
        }

        foreach ($holds as $booking) {
            // A hold that lapses in under an hour is the student's decision, not
            // the teacher's, but it is the only reason a slot may free up today.
            if ($booking->expires_at === null || $booking->expires_at->greaterThan(now()->addHour())) {
                continue;
            }

            $items[] = self::priorityItem(60, 'hold_expiring', self::holdCard($booking, $timezone, $earlyMinutes), [
                'eyebrow' => __('Hold expiring'),
                'body' => __('The slot is reserved until :time. If the student does not pay, the slot is released.', [
                    'time' => $booking->expires_at->copy()->setTimezone($timezone)->format('H:i'),
                ]),
                'tone' => 'amber',
            ]);
        }

        usort($items, fn (array $a, array $b) => [$a['rank'], $a['id']] <=> [$b['rank'], $b['id']]);

        return array_values(array_map(function (array $item) {
            unset($item['rank']);

            return $item;
        }, $items));
    }

    /**
     * @param  array<string, mixed>  $card
     * @param  array<string, string>  $copy
     * @return array<string, mixed>
     */
    private static function priorityItem(int $rank, string $kind, array $card, array $copy): array
    {
        $actions = [];

        if ($card['actions']['canComplete']) {
            $actions[] = [
                'type' => 'form',
                'label' => __('Mark complete'),
                'url' => $card['actions']['complete'],
                'tone' => 'primary',
            ];
        } elseif ($card['actions']['canStart']) {
            $actions[] = [
                'type' => 'form',
                'label' => __('Start lesson'),
                'url' => $card['actions']['start'],
                'tone' => 'primary',
            ];
        }

        if ($card['actions']['canNoShow']) {
            $actions[] = [
                'type' => 'form',
                'label' => __('No-show'),
                'url' => $card['actions']['noShow'],
                'tone' => 'ghost',
                'confirm' => true,
            ];
        }

        if ($card['canJoin']) {
            $actions[] = [
                'type' => 'link',
                'label' => __('Join classroom'),
                'url' => $card['joinUrl'],
                'tone' => 'primary',
            ];
        }

        $actions[] = [
            'type' => 'link',
            'label' => __('Open lesson'),
            'url' => $card['url'],
            'tone' => 'ghost',
        ];

        return [
            'rank' => $rank,
            'id' => $card['id'],
            'kind' => $kind,
            'tone' => $copy['tone'] ?? 'amber',
            'status' => $card['status'],
            'statusLabel' => $card['statusLabel'],
            'statusTone' => $card['statusTone'],
            'icon' => self::priorityIcon($kind),
            'eyebrow' => $copy['eyebrow'],
            'body' => $copy['body'],
            'title' => $card['learnerName'],
            'meta' => array_values(array_filter([
                $card['title'],
                $card['startsLabel'].'–'.$card['endTimeLabel'],
                $card['durationLabel'],
            ])),
            'url' => $card['url'],
            'actions' => $actions,
        ];
    }

    private static function priorityIcon(string $kind): string
    {
        return match ($kind) {
            'needs_outcome' => 'check-circle',
            'in_progress' => 'video',
            'join_now' => 'play',
            'starting_soon' => 'clock',
            'hold_expiring' => 'alert',
            default => 'calendar',
        };
    }

    /**
     * The upcoming lessons grouped by the day they fall on in the teacher's own
     * timezone — "Today", "Tomorrow", then the weekday.
     *
     * @param  Collection<int, Booking>  $upcoming
     * @return list<array<string, mixed>>
     */
    private static function agenda(Collection $upcoming, string $timezone, int $earlyMinutes): array
    {
        return $upcoming
            ->groupBy(fn (Booking $booking) => $booking->starts_at->copy()->setTimezone($timezone)->toDateString())
            ->map(function (Collection $group, string $key) use ($timezone, $earlyMinutes) {
                $day = $group->first()->starts_at->copy()->setTimezone($timezone);

                return [
                    'key' => $key,
                    'label' => self::dayLabel($day),
                    'dateLabel' => $day->format('D d M'),
                    'count' => $group->count(),
                    'countLabel' => trans_choice(':count lesson|:count lessons', $group->count(), ['count' => $group->count()]),
                    'minutesLabel' => __(':minutes min', [
                        'minutes' => $group->sum(fn (Booking $booking) => $booking->durationMinutes()),
                    ]),
                    'lessons' => $group
                        ->map(fn (Booking $booking) => self::card($booking, $timezone, $earlyMinutes))
                        ->values()
                        ->all(),
                ];
            })
            ->values()
            ->all();
    }

    private static function dayLabel(CarbonInterface $day): string
    {
        $today = now()->copy()->setTimezone($day->getTimezone());

        if ($day->isSameDay($today)) {
            return __('Today');
        }

        if ($day->isSameDay($today->copy()->addDay())) {
            return __('Tomorrow');
        }

        return $day->format('l');
    }

    /**
     * @return array<string, mixed>
     */
    private static function card(Booking $booking, string $timezone, int $earlyMinutes): array
    {
        $starts = $booking->starts_at->copy()->setTimezone($timezone);
        $ends = $booking->ends_at->copy()->setTimezone($timezone);
        $learner = $booking->learner_name ?: $booking->student->name;
        $title = collect([$booking->subject?->name, $booking->lesson?->name])->filter()->implode(' · ');

        return [
            'id' => $booking->id,
            'url' => route('teacher.bookings.show', $booking),
            'status' => $booking->status->value,
            'statusLabel' => $booking->status->label(),
            'statusTone' => self::tone($booking->status),
            'learnerName' => $learner,
            'learnerInitials' => self::initials($learner),
            'learnerGrade' => $booking->learnerGrade?->label,
            'subject' => $booking->subject?->name,
            'lesson' => $booking->lesson?->name,
            'title' => $title !== '' ? $title : __('Tutoring lesson'),
            'durationLabel' => $booking->durationMinutes().' '.__('min'),
            'startsAt' => $starts->toIso8601String(),
            'startsLabel' => $starts->format('D d M, H:i'),
            'timeLabel' => $starts->format('H:i'),
            'endTimeLabel' => $ends->format('H:i'),
            'payoutLabel' => platform_settings()->formatMinor((int) $booking->teacher_payout_minor),
            'joinUrl' => $booking->canJoinNow() ? route('classroom.show', $booking) : null,
            'canJoin' => $booking->canJoinNow(),
            'actions' => [
                'start' => route('teacher.bookings.start', $booking),
                'complete' => route('teacher.bookings.complete', $booking),
                'noShow' => route('teacher.bookings.no-show', $booking),
                'canStart' => self::canStart($booking, $earlyMinutes),
                'canComplete' => self::canComplete($booking),
                'canNoShow' => self::canNoShow($booking),
            ],
        ];
    }

    /**
     * A hold is not a lesson yet, so it carries the pay-by deadline instead of
     * the start/finish actions.
     *
     * @return array<string, mixed>
     */
    private static function holdCard(Booking $booking, string $timezone, int $earlyMinutes): array
    {
        return [
            ...self::card($booking, $timezone, $earlyMinutes),
            'expiresAt' => $booking->expires_at?->toIso8601String(),
            'payByLabel' => $booking->expires_at?->isFuture()
                ? __('Pay within :time', ['time' => $booking->expires_at->diffForHumans()])
                : null,
            'expiresLabel' => $booking->expires_at?->copy()->setTimezone($timezone)->format('H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function historyRow(Booking $booking, string $timezone): array
    {
        $starts = $booking->starts_at->copy()->setTimezone($timezone);
        $learner = $booking->learner_name ?: $booking->student->name;

        return [
            'id' => $booking->id,
            'url' => route('teacher.bookings.show', $booking),
            'status' => $booking->status->value,
            'statusLabel' => $booking->status->label(),
            'statusTone' => self::tone($booking->status),
            'dateLabel' => $starts->format('D d M'),
            'timeLabel' => $starts->format('H:i'),
            'learnerName' => $learner,
            'learnerInitials' => self::initials($learner),
            'learnerGrade' => $booking->learnerGrade?->label,
            'title' => collect([$booking->subject?->name, $booking->lesson?->name])->filter()->implode(' · '),
            'payoutLabel' => platform_settings()->formatMinor((int) $booking->teacher_payout_minor),
        ];
    }

    /**
     * @param  Collection<int, Booking>  $week
     * @param  Collection<int, Booking>  $holds
     * @return array<string, int>
     */
    private static function stats(Collection $week, Collection $holds, string $timezone): array
    {
        $todayKey = now()->copy()->setTimezone($timezone)->toDateString();

        $today = $week->filter(
            fn (Booking $booking) => $booking->starts_at->copy()->setTimezone($timezone)->toDateString() === $todayKey,
        );

        return [
            'todayCount' => $today->count(),
            'todayMinutes' => (int) $today->sum(fn (Booking $booking) => $booking->durationMinutes()),
            'weekCount' => $week->count(),
            'weekMinutes' => (int) $week->sum(fn (Booking $booking) => $booking->durationMinutes()),
            'holdCount' => $holds->count(),
            'holdSoonCount' => $holds->filter(
                fn (Booking $booking) => $booking->expires_at?->lessThanOrEqualTo(now()->addHour()),
            )->count(),
        ];
    }

    /**
     * @return array{message: string, tone: string, icon: string|null}|null
     */
    private static function flash(): ?array
    {
        return BookingDisplay::flash();
    }

    /**
     * @return array<string, mixed>
     */
    private static function copy(string $timezone): array
    {
        return [
            'hero' => [
                'eyebrow' => __('My schedule'),
                'title' => __('What is next, and what needs you'),
                'intro' => __('Your booked lessons, the holds students are still paying for and the history behind you — ordered by what to do next.'),
                'timezone' => __('Times shown in :timezone.', ['timezone' => $timezone]),
                'availability' => __('Edit availability'),
                'today' => __('Today'),
            ],
            'stats' => [
                'label' => __('Your week at a glance'),
                'today' => ':count lesson today|:count lessons today',
                'week' => ':count lesson in the next 7 days|:count lessons in the next 7 days',
                'minutes' => ':minutes min booked',
                'holds' => ':count hold awaiting payment|:count holds awaiting payment',
            ],
            'next' => [
                'title' => __('Next lesson'),
                'join' => __('Join classroom'),
                'open' => __('Open lesson'),
                'start' => __('Start lesson'),
                'startsIn' => __('Starts in :time'),
                'started' => __('Started'),
            ],
            'priority' => [
                'title' => __('Do next'),
                'description' => __('The lessons that need a decision now — most urgent first.'),
                'emptyTitle' => __('Nothing needs you right now'),
                'emptyBody' => __('Your next lesson is booked and nothing is waiting on you. This list fills itself as lessons start and finish.'),
                'emptyBodyIdle' => __('No lessons are booked yet. Publish your availability and this list fills itself as soon as a student books.'),
            ],
            'agenda' => [
                'title' => __('Upcoming lessons'),
                'description' => __('Grouped by day in your timezone.'),
                'emptyTitle' => __('No lessons booked yet'),
                'emptyBody' => __('Keep your availability and lessons up to date — students book directly into the open slots you publish.'),
                'emptyCta' => __('Open availability'),
                'payout' => __('You earn'),
            ],
            'holds' => [
                'title' => __('Awaiting payment'),
                'description' => __('Students holding a slot. Each one frees up automatically if payment does not arrive.'),
                'emptyTitle' => __('No unpaid holds'),
                'emptyBody' => __('Every slot is either booked and paid, or still open in your availability.'),
                'payWithin' => __('Pay within :time'),
                'expired' => __('Hold expired'),
                'expires' => __('Expires :time', ['time' => '']),
            ],
            'history' => [
                'title' => __('Recent history'),
                'description' => __('The last ten lessons that finished or were released.'),
                'emptyTitle' => __('Nothing in your history yet'),
                'emptyBody' => __('Completed, cancelled and no-show lessons will appear here with their payout.'),
                'view' => __('View'),
            ],
            'actions' => [
                'start' => __('Start lesson'),
                'complete' => __('Mark complete'),
                'noShow' => __('No-show'),
                'join' => __('Join classroom'),
                'open' => __('Open lesson'),
                'confirmNoShowTitle' => __('Mark this lesson as a no-show?'),
                'confirmNoShowBody' => __('The student is not charged and this cannot be undone from here.'),
                'confirm' => __('Yes, mark no-show'),
                'cancel' => __('Keep the lesson'),
                'busy' => __('Sending…'),
            ],
            'tabs' => [
                'label' => __('Schedule views'),
            ],
        ];
    }

    private static function canStart(Booking $booking, int $earlyMinutes): bool
    {
        return $booking->status === BookingStatus::Confirmed
            && now()->greaterThanOrEqualTo($booking->starts_at->copy()->subMinutes($earlyMinutes));
    }

    private static function canComplete(Booking $booking): bool
    {
        return $booking->status->isLive()
            && ($booking->started_at !== null || now()->greaterThanOrEqualTo($booking->starts_at));
    }

    private static function canNoShow(Booking $booking): bool
    {
        return $booking->status === BookingStatus::Confirmed
            && now()->greaterThanOrEqualTo($booking->starts_at);
    }

    private static function tone(BookingStatus $status): string
    {
        return BookingDisplay::tone($status);
    }

    private static function initials(string $name): string
    {
        return BookingDisplay::initials($name);
    }

    /**
     * "in 25 min", "in 2 h 10 min", "5 min ago" — short enough for a chip and
     * stable in a test, unlike diffForHumans()'s wording.
     */
    private static function relative(CarbonInterface $at): string
    {
        $minutes = (int) round(now()->diffInMinutes($at, false));
        $future = $minutes >= 0;
        $minutes = abs($minutes);

        if ($minutes < 1) {
            return __('now');
        }

        if ($minutes < 60) {
            $label = __(':count min', ['count' => $minutes]);
        } else {
            $hours = intdiv($minutes, 60);
            $rest = $minutes % 60;
            $label = $rest > 0
                ? __(':hours h :minutes min', ['hours' => $hours, 'minutes' => $rest])
                : __(':hours h', ['hours' => $hours]);
        }

        return $future ? __('in :time', ['time' => $label]) : __(':time ago', ['time' => $label]);
    }
}
