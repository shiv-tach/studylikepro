<?php

namespace App\Support;

use App\Enums\BookingStatus;
use App\Enums\EarningStatus;
use App\Enums\MeetingStatus;
use App\Models\Booking;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Gate;

/**
 * The JSON island behind /teacher/schedule/{booking}.
 *
 * Blade still owns every permission, clock, price and route, so the Vue app
 * never decides whether an action is legal or formats a date. The page answers
 * two questions before it shows the paperwork: what is the single next thing to
 * do, and what else is waiting — ranked exactly like the schedule list, so the
 * two screens never disagree.
 */
final class TeacherBookingShowConfig
{
    public static function build(Booking $booking, User $viewer, string $timezone, string $preset): array
    {
        $status = $booking->status;
        $earlyMinutes = (int) config('studylikepro.booking.teacher_start_early_minutes');

        $starts = $booking->starts_at->copy()->setTimezone($timezone);
        $ends = $booking->ends_at->copy()->setTimezone($timezone);
        $learner = $booking->learner_name ?: $booking->student->name;

        $isOwner = $booking->teacherProfile?->user_id === $viewer->id;
        $isAdmin = $viewer->isAdmin();

        $startWindowOpensAt = $booking->starts_at->copy()->subMinutes($earlyMinutes);

        // Mirrors BookingPolicy + BookingTransitionService: the page only offers
        // a transition the controller would actually accept.
        $canStart = $isOwner
            && $status === BookingStatus::Confirmed
            && now()->greaterThanOrEqualTo($startWindowOpensAt);
        $canComplete = $isOwner
            && $status->isLive()
            && ($booking->started_at !== null || now()->greaterThanOrEqualTo($booking->starts_at));
        $canNoShow = $isOwner
            && $status === BookingStatus::Confirmed
            && now()->greaterThanOrEqualTo($booking->starts_at);
        $canCancel = Gate::allows('cancel', $booking);
        $canJoin = $booking->canJoinNow() && ! $isAdmin;
        $canEnterRoom = Gate::allows('join', $booking);
        $canMessage = $booking->conversation !== null && ! $isAdmin;

        $actions = [
            'canStart' => $canStart,
            'canComplete' => $canComplete,
            'canNoShow' => $canNoShow,
            'canCancel' => $canCancel,
            'canJoin' => $canJoin,
            'canMessage' => $canMessage,
            'start' => route('teacher.bookings.start', $booking),
            'complete' => route('teacher.bookings.complete', $booking),
            'noShow' => route('teacher.bookings.no-show', $booking),
            'cancel' => route('teacher.bookings.cancel', $booking),
            'cancelUrl' => route('teacher.bookings.cancel', $booking),
            'joinUrl' => $canEnterRoom ? route('classroom.show', $booking) : null,
            'messageUrl' => $canMessage ? route('messages.show', $booking->conversation) : null,
        ];

        $join = self::join($booking, $timezone, $canJoin, $canEnterRoom, $canMessage);
        $journey = self::journey($booking, $timezone, $actions, $isOwner);
        $priority = $isOwner ? self::priority($booking, $actions) : [];

        return [
            'appName' => config('app.name', 'Studylikepro'),
            'csrfToken' => csrf_token(),
            'timezone' => $timezone,
            'theme' => ['preset' => $preset],
            'viewer' => [
                'isOwner' => $isOwner,
                'isAdmin' => $isAdmin,
            ],
            'flash' => BookingDisplay::flash(),
            'error' => session('errors')?->first('status') ?: null,
            'header' => [
                'backLabel' => __('My lessons'),
                'backUrl' => route('teacher.schedule.index'),
                'learner' => $learner,
                'learnerInitials' => BookingDisplay::initials($learner),
                'statusLabel' => $status->label(),
                'statusTone' => BookingDisplay::tone($status),
            ],
            'lesson' => [
                'learner' => $learner,
                'learnerGrade' => $booking->learnerGrade?->label,
                'account' => $booking->student->name,
                'subject' => $booking->subject?->name,
                'lesson' => $booking->lesson?->name,
                'title' => collect([$booking->subject?->name, $booking->lesson?->name])
                    ->filter()
                    ->implode(' · ') ?: __('Tutoring lesson'),
                'dateLabel' => $starts->format('D d M Y'),
                'timeRange' => $starts->format(TimeFormat::TIME).'–'.$ends->format(TimeFormat::TIME),
                'durationLabel' => __(':minutes minutes', ['minutes' => $booking->durationMinutes()]),
                'timezone' => $timezone,
                'reference' => '#'.str_pad((string) $booking->id, 6, '0', STR_PAD_LEFT),
                'bookedOn' => $booking->created_at->copy()->setTimezone($timezone)->format(TimeFormat::DATE_TIME),
                'startsAt' => $booking->starts_at->toIso8601String(),
                'endsAt' => $booking->ends_at->toIso8601String(),
                'startWindowOpensAt' => $startWindowOpensAt->toIso8601String(),
                'startWindowLabel' => $startWindowOpensAt->copy()->setTimezone($timezone)->format(TimeFormat::TIME),
                'payoutLabel' => platform_settings()->formatMinor((int) $booking->teacher_payout_minor),
            ],
            'join' => $join,
            'next' => self::next($booking, $timezone, $join, $actions, $isOwner, $isAdmin),
            'priority' => $priority,
            'journey' => $journey,
            'money' => self::money($booking),
            'timeline' => [
                'steps' => collect($booking->timeline())
                    ->map(fn (array $step) => [
                        'key' => $step['key'],
                        'label' => $step['label'],
                        'at' => $step['at']?->copy()->setTimezone($timezone)->format(TimeFormat::DATE_TIME),
                        'description' => $step['description'],
                    ])
                    ->values()
                    ->all(),
                'cancellationReason' => $booking->cancellation_reason,
            ],
            'actions' => $actions,
            'cancel' => $canCancel ? [
                'title' => __('Cancel this lesson?'),
                'body' => __('The student is notified straight away and the slot is released. Cancelling late counts against your reliability score.'),
                'reasonLabel' => __('Reason (shared with the student)'),
                'keepLabel' => __('Keep lesson'),
                'submitLabel' => __('Cancel lesson'),
                'url' => route('teacher.bookings.cancel', $booking),
                'error' => session('errors')?->first('reason') ?: null,
            ] : null,
            'refreshAt' => self::refreshAt($booking, $canStart, $canJoin),
            'copy' => self::copy($timezone),
            'urls' => [
                'schedule' => route('teacher.schedule.index'),
                'earnings' => route('teacher.earnings.index'),
                'availability' => route('teacher.availability.index'),
                'messages' => route('messages.index'),
            ],
        ];
    }

    /**
     * The classroom's state, decided by the booking's own clock and meeting
     * record. The teacher still needs the room link while the status is live,
     * even before the join window opens.
     *
     * @return array<string, mixed>
     */
    private static function join(
        Booking $booking,
        string $timezone,
        bool $canJoin,
        bool $canEnterRoom,
        bool $canMessage,
    ): array {
        $opensAt = $booking->joinOpensAt()->copy()->setTimezone($timezone);
        $closesAt = $booking->joinClosesAt()->copy()->setTimezone($timezone);
        $windowClosed = now()->greaterThan($booking->joinClosesAt());

        if ($canJoin) {
            $state = 'open';
            $title = __('Your classroom is open');
            $body = __('Open now — your student can join. The room stays open until :time.', [
                'time' => $closesAt->format(TimeFormat::TIME),
            ]);
        } elseif ($windowClosed) {
            $state = 'closed';
            $title = __('The classroom window has closed');
            $body = __('The join window ended on :date, so the room is no longer available.', [
                'date' => $closesAt->format('D d M, '.TimeFormat::TIME),
            ]);
        } elseif ($booking->meeting_status === MeetingStatus::Failed) {
            $state = 'failed';
            $title = __('The room could not be opened');
            $body = __('Try again from the classroom page.');
        } elseif ($booking->status->isLive()) {
            $state = 'waiting';
            $title = __('Classroom opens :minutes minutes early', [
                'minutes' => $booking->joinOpensBeforeMinutes(),
            ]);
            $body = __('Joins open at :time on :date. The room itself follows the lesson status.', [
                'time' => $opensAt->format(TimeFormat::TIME),
                'date' => $opensAt->format('D d M'),
            ]);
        } else {
            $state = 'closed';
            $title = __('The classroom follows the lesson status');
            $body = __('This lesson is not live, so there is nothing to open yet.');
        }

        return [
            'state' => $state,
            'title' => $title,
            'body' => $body,
            'canJoin' => $canJoin,
            'joinUrl' => $canJoin ? route('classroom.show', $booking) : null,
            'roomUrl' => $canEnterRoom && ! $windowClosed ? route('classroom.show', $booking) : null,
            'enterLabel' => $canJoin ? __('Join lesson') : __('Live classroom'),
            'canMessage' => $canMessage,
            'messageUrl' => $canMessage ? route('messages.show', $booking->conversation) : null,
            'messageLabel' => __('Message :name', ['name' => $booking->learner_name ?: $booking->student->name]),
            'opensAt' => $booking->starts_at->isFuture() ? $opensAt->toIso8601String() : null,
            'closesAt' => $closesAt->toIso8601String(),
        ];
    }

    /**
     * The single answer to "what now", derived from the same precedence the
     * priority queue uses.
     *
     * @param  array<string, mixed>  $join
     * @param  array<string, mixed>  $actions
     * @return array<string, mixed>
     */
    private static function next(
        Booking $booking,
        string $timezone,
        array $join,
        array $actions,
        bool $isOwner,
        bool $isAdmin,
    ): array {
        $status = $booking->status;
        $starts = $booking->starts_at->copy()->setTimezone($timezone);

        if ($isAdmin && ! $isOwner) {
            return self::nextItem('read_only', 'slate', 'eye', __('Administrator view'), [
                'body' => __('You are inspecting this lesson as an administrator. Transition buttons stay hidden because the policy only lets the teacher run them.'),
                'secondary' => [],
            ]);
        }

        if ($status === BookingStatus::Disputed) {
            return self::nextItem('disputed', 'rose', 'shield', __('Under dispute'), [
                'body' => __('This lesson is with support. Nothing is expected from you while it is reviewed.'),
                'secondary' => [],
            ]);
        }

        if ($status === BookingStatus::PendingPayment) {
            $expires = $booking->expires_at;

            return self::nextItem('hold', 'amber', 'clock', __('Waiting on payment'), [
                'body' => $expires && $expires->isFuture()
                    ? __('The slot is held until :time (:relative). It frees up automatically if the student does not pay.', [
                        'time' => $expires->copy()->setTimezone($timezone)->format(TimeFormat::TIME),
                        'relative' => $expires->diffForHumans(),
                    ])
                    : __('The payment window has closed and the slot will be released.'),
                'deadline' => $expires?->toIso8601String(),
                'countdownPrefix' => __('Payment time ends in :time'),
                'countdownZero' => __('Payment time ran out'),
                'secondary' => self::cancelAction($actions),
            ]);
        }

        if ($status->isFinal() || $status === BookingStatus::Resolved) {
            return self::nextItem('closed', 'slate', 'check-circle', __('Lesson closed'), [
                'body' => self::closedBody($booking),
                'secondary' => [
                    self::linkAction(__('Back to my lessons'), route('teacher.schedule.index'), 'ghost'),
                ],
            ]);
        }

        if ($status === BookingStatus::InProgress) {
            if ($join['canJoin']) {
                return self::nextItem('live_join', 'emerald', 'video', __('Classroom open'), [
                    'body' => __('Your student can join now — open the room and start the lesson.'),
                    'primary' => self::linkAction(__('Join classroom'), $join['joinUrl'], 'primary'),
                    'secondary' => array_values(array_filter([
                        $actions['canComplete'] ? self::formAction(__('Mark as delivered'), $actions['complete'], 'primary') : null,
                        $join['roomUrl'] ? self::linkAction(__('Live classroom'), $join['roomUrl'], 'ghost') : null,
                    ])),
                ]);
            }

            return self::nextItem('live', 'primary', 'video', __('In progress'), [
                'body' => __('The lesson is running. Mark it delivered as soon as you finish so the student gets their receipt and your earnings settle.'),
                'primary' => $actions['canComplete']
                    ? self::formAction(__('Mark as delivered'), $actions['complete'], 'primary')
                    : null,
                'secondary' => array_values(array_filter([
                    $join['roomUrl'] ? self::linkAction(__('Live classroom'), $join['roomUrl'], 'ghost') : null,
                ])),
            ]);
        }

        if ($booking->ends_at->isPast()) {
            return self::nextItem('outcome', 'amber', 'check-circle', __('Needs an outcome'), [
                'body' => __('The lesson time has passed. Close it so the student gets their receipt and your earnings settle.'),
                'primary' => $actions['canComplete']
                    ? self::formAction(__('Mark as delivered'), $actions['complete'], 'primary')
                    : null,
                'secondary' => array_values(array_filter([
                    $actions['canNoShow']
                        ? self::formAction(__('Student did not show'), $actions['noShow'], 'ghost', confirm: true)
                        : null,
                    self::cancelAction($actions)[0] ?? null,
                ])),
            ]);
        }

        if ($actions['canStart']) {
            return self::nextItem('start_now', 'emerald', 'play', __('Ready to start'), [
                'body' => __('The classroom is open a few minutes early, so you can settle in before the student arrives.'),
                'primary' => $actions['canJoin']
                    ? self::linkAction(__('Join classroom'), $join['joinUrl'], 'primary')
                    : self::formAction(__('Start lesson'), $actions['start'], 'primary'),
                'secondary' => array_values(array_filter([
                    $actions['canStart'] && $actions['canJoin']
                        ? self::formAction(__('Start lesson'), $actions['start'], 'ghost')
                        : null,
                    $join['messageUrl'] ? self::linkAction($join['messageLabel'], $join['messageUrl'], 'ghost') : null,
                ])),
            ]);
        }

        return self::nextItem('upcoming', 'sky', 'calendar', __('Up next'), [
            'body' => __('The lesson is booked. You can start it from :time on :date.', [
                'time' => $booking->starts_at->copy()->subMinutes((int) config('studylikepro.booking.teacher_start_early_minutes'))->setTimezone($timezone)->format(TimeFormat::TIME),
                'date' => $starts->format('D d M'),
            ]),
            'deadline' => $booking->starts_at->toIso8601String(),
            'countdownPrefix' => __('Starts in :time'),
            'countdownZero' => __('Started'),
            'secondary' => array_values(array_filter([
                $join['messageUrl'] ? self::linkAction($join['messageLabel'], $join['messageUrl'], 'ghost') : null,
                self::cancelAction($actions)[0] ?? null,
            ])),
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private static function nextItem(string $kind, string $tone, string $icon, string $eyebrow, array $extra = []): array
    {
        return [
            'kind' => $kind,
            'tone' => $tone,
            'icon' => $icon,
            'eyebrow' => $eyebrow,
            'body' => $extra['body'] ?? '',
            'primary' => $extra['primary'] ?? null,
            'secondary' => $extra['secondary'] ?? [],
            'deadline' => $extra['deadline'] ?? null,
            'countdownPrefix' => $extra['countdownPrefix'] ?? null,
            'countdownZero' => $extra['countdownZero'] ?? null,
        ];
    }

    /**
     * Everything else that needs the teacher, most urgent first.
     *
     * @param  array<string, mixed>  $actions
     * @return list<array<string, mixed>>
     */
    private static function priority(Booking $booking, array $actions): array
    {
        $status = $booking->status;
        $items = [];

        if ($status === BookingStatus::Confirmed && $booking->ends_at->isPast()) {
            $items[] = self::priorityItem(10, 'outcome', 'amber', 'check-circle', __('Needs an outcome'),
                __('The scheduled time has passed. Mark the lesson delivered, or record a no-show.'),
                array_values(array_filter([
                    $actions['canComplete'] ? self::formAction(__('Mark as delivered'), $actions['complete'], 'primary') : null,
                    $actions['canNoShow'] ? self::formAction(__('Student did not show'), $actions['noShow'], 'ghost', confirm: true) : null,
                ])),
                $booking->learner_name ?: $booking->student->name);
        }

        if ($status === BookingStatus::InProgress && $actions['canJoin']) {
            $items[] = self::priorityItem(20, 'join', 'emerald', 'video', __('Classroom open'),
                __('Your student can join now. Open the room, then mark the lesson delivered when you are done.'),
                array_values(array_filter([
                    self::linkAction(__('Join classroom'), $actions['joinUrl'], 'primary'),
                    $actions['canComplete'] ? self::formAction(__('Mark as delivered'), $actions['complete'], 'ghost') : null,
                ])),
                $booking->learner_name ?: $booking->student->name);
        }

        if ($status === BookingStatus::InProgress) {
            $items[] = self::priorityItem(30, 'deliver', 'primary', 'check-circle', __('Close the lesson'),
                __('Mark the lesson delivered once you finish — the student receipt and your earning depend on it.'),
                $actions['canComplete'] ? [self::formAction(__('Mark as delivered'), $actions['complete'], 'primary')] : [],
                $booking->learner_name ?: $booking->student->name);
        }

        if ($status === BookingStatus::Confirmed && $actions['canStart'] && ! $booking->ends_at->isPast()) {
            $items[] = self::priorityItem(40, 'start', 'emerald', 'play', __('Start the lesson'),
                __('The classroom window is open. Start the lesson when you and the student are ready.'),
                [self::formAction(__('Start lesson'), $actions['start'], 'primary')],
                $booking->learner_name ?: $booking->student->name);
        }

        if ($status === BookingStatus::Confirmed
            && ! $booking->ends_at->isPast()
            && $booking->starts_at->lessThanOrEqualTo(now()->addHour())) {
            $items[] = self::priorityItem(50, 'prepare', 'sky', 'clock', __('Prepare for the lesson'),
                __('Check the learner grade and lesson topic, then open the classroom a few minutes early.'),
                array_values(array_filter([
                    $actions['messageUrl'] ? self::linkAction(__('Message learner'), $actions['messageUrl'], 'ghost') : null,
                ])),
                $booking->learner_name ?: $booking->student->name);
        }

        if ($status === BookingStatus::PendingPayment && $booking->expires_at?->isFuture()) {
            $items[] = self::priorityItem(60, 'hold', 'amber', 'alert', __('Payment time nearly up'),
                __('The time slot is reserved until the student pays. It is released automatically if they do not pay.'),
                self::cancelAction($actions),
                $booking->learner_name ?: $booking->student->name);
        }

        usort($items, fn (array $a, array $b) => $a['rank'] <=> $b['rank']);

        return array_values(array_map(function (array $item) {
            unset($item['rank']);

            return $item;
        }, $items));
    }

    /**
     * @param  list<array<string, mixed>>  $actions
     * @return array<string, mixed>
     */
    private static function priorityItem(
        int $rank,
        string $kind,
        string $tone,
        string $icon,
        string $eyebrow,
        string $body,
        array $actions,
        string $title,
    ): array {
        return [
            'rank' => $rank,
            'kind' => $kind,
            'tone' => $tone,
            'icon' => $icon,
            'eyebrow' => $eyebrow,
            'title' => $title,
            'body' => $body,
            'actions' => $actions,
        ];
    }

    /**
     * The forward-looking rail: what has happened, what is current and what is
     * still ahead. Terminal states collapse the rest into "closed".
     *
     * @param  array<string, mixed>  $actions
     * @return list<array<string, mixed>>
     */
    private static function journey(Booking $booking, string $timezone, array $actions, bool $isOwner): array
    {
        $status = $booking->status;
        $earning = $booking->earning;
        $terminal = $status->isFinal() || $status === BookingStatus::Disputed;
        $ended = $booking->ends_at->isPast();

        $steps = [];

        $steps[] = [
            'key' => 'confirmed',
            'label' => $status === BookingStatus::PendingPayment
                ? __('Awaiting payment')
                : __('Lesson confirmed'),
            'description' => $status === BookingStatus::PendingPayment
                ? __('Waiting for the student to complete payment.')
                : __('The slot is yours and the student has been told.'),
            'state' => match (true) {
                $booking->confirmed_at !== null => 'done',
                $status === BookingStatus::PendingPayment => 'current',
                default => 'done',
            },
            'at' => $booking->confirmed_at?->copy()->setTimezone($timezone)->format('d M'),
            'action' => null,
        ];

        $steps[] = [
            'key' => 'classroom',
            'label' => __('Open the classroom'),
            'description' => __('Start the lesson from the window that opens :minutes minutes early.', [
                'minutes' => (int) config('studylikepro.booking.teacher_start_early_minutes'),
            ]),
            'state' => match (true) {
                $booking->started_at !== null => 'done',
                $terminal => 'skipped',
                $ended => 'skipped',
                $status === BookingStatus::Confirmed && $actions['canStart'] => 'current',
                default => 'upcoming',
            },
            'at' => $booking->started_at?->copy()->setTimezone($timezone)->format(TimeFormat::TIME),
            'action' => $isOwner && $status === BookingStatus::Confirmed && $actions['canStart'] && ! $ended
                ? self::formAction(__('Start lesson'), $actions['start'], 'primary')
                : null,
        ];

        $steps[] = [
            'key' => 'delivered',
            'label' => __('Mark as delivered'),
            'description' => __('Closing the lesson sends the student their receipt and releases your earning.'),
            'state' => match (true) {
                $booking->completed_at !== null => 'done',
                $terminal => 'skipped',
                $actions['canComplete'] => 'current',
                default => 'upcoming',
            },
            'at' => $booking->completed_at?->copy()->setTimezone($timezone)->format(TimeFormat::TIME),
            'action' => $isOwner && $actions['canComplete']
                ? self::formAction(__('Mark delivered'), $actions['complete'], 'primary')
                : null,
        ];

        $steps[] = [
            'key' => 'settled',
            'label' => __('Earnings settle'),
            'description' => $earning === null
                ? __('The payout appears here once the lesson is paid.')
                : match ($earning->status) {
                    EarningStatus::Paid => __('Paid out :date.', ['date' => $earning->paid_at?->format('d M Y') ?? '']),
                    EarningStatus::Eligible => __('Available for payout in your earnings ledger.'),
                    EarningStatus::Reversed => __('Reversed by a refund.'),
                    default => __('Becomes available once the lesson is delivered.'),
                },
            'state' => match (true) {
                $earning?->status === EarningStatus::Paid,
                $earning?->status === EarningStatus::Eligible => 'done',
                $terminal && $status !== BookingStatus::Completed => 'skipped',
                $booking->completed_at !== null => 'current',
                default => 'upcoming',
            },
            'at' => $earning?->paid_at?->copy()->setTimezone($timezone)->format('d M')
                ?? $earning?->available_at?->copy()->setTimezone($timezone)->format('d M'),
            'action' => null,
        ];

        return $steps;
    }

    /**
     * @return array<string, mixed>
     */
    private static function money(Booking $booking): array
    {
        $money = fn (int $minor) => platform_settings()->formatMinor($minor);
        $earning = $booking->earning;

        $note = null;
        $noteTone = 'slate';

        if ($earning !== null) {
            if ($earning->reversed_minor > 0) {
                $note = __('This lesson was refunded, so :amount was reversed from your ledger.', [
                    'amount' => $money($earning->reversed_minor),
                ]);
                $noteTone = 'rose';
            } elseif ($earning->status === EarningStatus::Paid) {
                $note = __('Paid out :date.', [
                    'date' => $earning->paid_at?->format('d M Y') ?? '',
                ]);
                $noteTone = 'emerald';
            } elseif ($earning->status === EarningStatus::Eligible) {
                $note = __('Available for payout in your earnings ledger.');
                $noteTone = 'emerald';
            } else {
                $note = __('Held until the lesson is delivered, then it becomes available for payout.');
                $noteTone = 'amber';
            }
        }

        return [
            'priceLabel' => __('Lesson price'),
            'price' => $money((int) $booking->price_minor),
            'feeLabel' => __('Platform fee (:percent%)', ['percent' => $booking->commission_percent]),
            'fee' => $money((int) $booking->platform_fee_minor),
            'payoutLabel' => __('You earn'),
            'payout' => $money((int) $booking->teacher_payout_minor),
            'note' => $note,
            'noteTone' => $noteTone,
            'earningStatusLabel' => $earning?->status->label(),
            'purchaseNote' => $booking->isPurchaseSession()
                ? __('This session is covered by the student purchase — no extra payment is due.')
                : null,
        ];
    }

    private static function closedBody(Booking $booking): string
    {
        return match ($booking->status) {
            BookingStatus::Completed => __('This lesson is delivered. Your earning moves to the earnings ledger for payout.'),
            BookingStatus::Cancelled => filled($booking->cancellation_reason)
                ? __('Cancelled by the :who. Reason: :reason', [
                    'who' => $booking->cancelled_by ?? __('platform'),
                    'reason' => $booking->cancellation_reason,
                ])
                : __('Cancelled by the :who. The slot is back on your calendar.', [
                    'who' => $booking->cancelled_by ?? __('platform'),
                ]),
            BookingStatus::Expired => __('The payment window closed, so the slot was released.'),
            BookingStatus::NoShow => __('Recorded as a no-show. No earning was created for this lesson.'),
            default => __('This lesson is closed.'),
        };
    }

    /**
     * The next instant the page's answer changes (start window, join window,
     * lesson end, hold expiry). The app reloads once, so the action appears
     * without the teacher refreshing by hand.
     */
    private static function refreshAt(Booking $booking, bool $canStart, bool $canJoin): ?string
    {
        $candidates = [];

        if ($booking->status === BookingStatus::PendingPayment) {
            $candidates[] = $booking->expires_at;
        } else {
            if ($booking->status === BookingStatus::Confirmed && ! $canStart) {
                $candidates[] = $booking->starts_at->copy()->subMinutes(
                    (int) config('studylikepro.booking.teacher_start_early_minutes'),
                );
            }

            if ($booking->status->isLive() && ! $canJoin) {
                $candidates[] = $booking->joinOpensAt();
            }

            if ($booking->status->isLive()) {
                $candidates[] = $booking->joinClosesAt();
                $candidates[] = $booking->ends_at;
            }
        }

        $next = collect($candidates)
            ->filter(fn (?CarbonInterface $at) => $at !== null && $at->isFuture())
            ->sort()
            ->first();

        return $next?->toIso8601String();
    }

    /**
     * @return array<string, mixed>
     */
    private static function formAction(string $label, ?string $url, string $tone, bool $confirm = false): array
    {
        return [
            'kind' => 'form',
            'label' => $label,
            'url' => $url,
            'tone' => $tone,
            'confirm' => $confirm,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function linkAction(string $label, ?string $url, string $tone): array
    {
        return [
            'kind' => 'link',
            'label' => $label,
            'url' => $url,
            'tone' => $tone,
            'confirm' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $actions
     * @return list<array<string, mixed>>
     */
    private static function cancelAction(array $actions): array
    {
        if (! $actions['canCancel']) {
            return [];
        }

        return [[
            'kind' => 'dialog',
            'label' => __('Cancel lesson'),
            'url' => null,
            'tone' => 'danger',
            'confirm' => false,
        ]];
    }

    /**
     * @return array<string, mixed>
     */
    private static function copy(string $timezone): array
    {
        return [
            'hero' => [
                'eyebrow' => __('Lesson detail'),
                'timezone' => __('Times shown in :timezone.', ['timezone' => $timezone]),
                'reference' => __('Reference'),
                'back' => __('Back to my lessons'),
                'payout' => __('You earn'),
            ],
            'next' => [
                'title' => __('Next step'),
                'doneTitle' => __('Nothing is waiting on you'),
                'refresh' => __('Refresh state'),
            ],
            'priority' => [
                'title' => __('Do next'),
                'description' => __('Everything this lesson needs from you, most urgent first.'),
                'emptyTitle' => __('Nothing else needs you'),
                'emptyBody' => __('The next action is in the card above. This list fills itself when the lesson window changes.'),
            ],
            'journey' => [
                'title' => __('Lesson flow'),
                'description' => __('Where this lesson is, and what comes next.'),
                'closed' => __('This lesson is closed.'),
            ],
            'details' => [
                'title' => __('Lesson details'),
                'learner' => __('Learner'),
                'account' => __('Account'),
                'grade' => __('Grade'),
                'subject' => __('Subject'),
                'lesson' => __('Topic'),
                'bookedOn' => __('Booked on'),
                'reference' => __('Reference'),
                'timezone' => __('Timezone'),
            ],
            'classroom' => [
                'title' => __('Live classroom'),
            ],
            'money' => [
                'title' => __('Earnings for this lesson'),
                'openLedger' => __('Open earnings'),
            ],
            'timeline' => [
                'title' => __('Status timeline'),
                'pending' => __('pending'),
            ],
            'actions' => [
                'title' => __('Lesson actions'),
                'start' => __('Start lesson'),
                'complete' => __('Mark as delivered'),
                'noShow' => __('Student did not show'),
                'cancel' => __('Cancel lesson'),
                'join' => __('Join lesson'),
                'open' => __('Open lesson'),
                'message' => __('Message learner'),
                'confirmNoShowTitle' => __('Mark this lesson as a no-show?'),
                'confirmNoShowBody' => __('The student is not charged and this cannot be undone from here.'),
                'confirm' => __('Yes, mark no-show'),
                'keep' => __('Keep the lesson'),
                'busy' => __('Sending…'),
                'none' => __('There is nothing to do on this lesson right now.'),
            ],
        ];
    }
}
