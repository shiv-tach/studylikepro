@php
    use App\Enums\BookingStatus;
    use App\Enums\MarkingOrderStatus;
    use App\Enums\PurchaseStatus;
    use App\Enums\ServiceType;

    // /teacher/orders/{purchase} is rendered by resources/js/teacher-order-show.
    // Blade owns every permission check, price, date, status tone and route, then
    // hands them over as one JSON island. The <noscript> block below repeats the
    // facts a teacher acts on without JavaScript.
    $themePreset = $userTheme['preset'] ?? 'classic';
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);
    $timezone = config('studylikepro.default_display_timezone');
    $date = fn ($moment) => display_datetime($moment?->copy()->setTimezone($timezone));

    $typeIcons = [
        ServiceType::Lesson->value => 'graduation',
        ServiceType::PaperMarking->value => 'clipboard',
        ServiceType::PaperDiscussion->value => 'chat',
        ServiceType::Qna->value => 'sparkles',
    ];

    $purchaseTone = fn (PurchaseStatus $status) => match ($status) {
        PurchaseStatus::PendingPayment => 'amber',
        PurchaseStatus::Active => 'primary',
        PurchaseStatus::Completed => 'emerald',
        PurchaseStatus::Disputed => 'rose',
        PurchaseStatus::Cancelled, PurchaseStatus::Expired, PurchaseStatus::Resolved => 'slate',
    };

    $sessionTone = fn (BookingStatus $status) => match ($status) {
        BookingStatus::PendingPayment => 'amber',
        BookingStatus::InProgress => 'primary',
        BookingStatus::Confirmed, BookingStatus::Completed => 'emerald',
        BookingStatus::NoShow, BookingStatus::Disputed => 'rose',
        BookingStatus::Cancelled, BookingStatus::Expired, BookingStatus::Resolved => 'slate',
    };

    $markingTone = fn (MarkingOrderStatus $status) => match ($status) {
        MarkingOrderStatus::Submitted => 'amber',
        MarkingOrderStatus::InProgress => 'primary',
        MarkingOrderStatus::Returned => 'sky',
        MarkingOrderStatus::Accepted => 'emerald',
        MarkingOrderStatus::Disputed => 'rose',
        MarkingOrderStatus::Resolved, MarkingOrderStatus::Cancelled => 'slate',
    };

    $earningTone = fn (\App\Enums\EarningStatus $status) => match ($status) {
        \App\Enums\EarningStatus::Pending => 'amber',
        \App\Enums\EarningStatus::Eligible => 'emerald',
        \App\Enums\EarningStatus::Paid => 'primary',
        \App\Enums\EarningStatus::Reversed => 'rose',
    };

    // A hold whose window has closed is already lost even though the scheduler
    // has not written the status yet — the same reading the orders list uses.
    $status = $purchase->isHoldLapsed() ? PurchaseStatus::Expired : $purchase->status;
    $isPending = $status === PurchaseStatus::PendingPayment;
    $isPlan = $purchase->isLessonPlan();
    $hasPaid = $purchase->paid_at !== null;
    $allocation = (int) $purchase->allocation_minutes;

    $sessions = $purchase->sessions;
    $countingSessions = $sessions->whereNotIn('status', [BookingStatus::Cancelled, BookingStatus::Expired]);
    $remaining = $isPlan && $allocation > 0
        ? max(0, $allocation - $countingSessions->sum(fn ($session) => $session->durationMinutes()))
        : null;
    $sessionsLeft = $isPlan && $purchase->max_sessions !== null
        ? max(0, (int) $purchase->max_sessions - $countingSessions->count())
        : null;

    // The one thread worth opening from here: the first session that has one.
    $conversation = $sessions->first(fn ($session) => $session->conversation !== null)?->conversation;

    $sessionRows = $sessions->map(fn ($session) => [
        'id' => $session->id,
        'url' => route('teacher.bookings.show', $session),
        'when' => display_datetime($session->starts_at->copy()->setTimezone($timezone), 'D, d M Y', ' · '),
        'durationLabel' => __(':minutes min', ['minutes' => $session->durationMinutes()]),
        'learnerName' => $session->learner_name ?: $purchase->student->name,
        'scope' => collect([$session->subject?->name, $session->lesson?->name])->filter()->implode(' · ') ?: null,
        'status' => [
            'label' => $session->status->label(),
            'tone' => $sessionTone($session->status),
        ],
        'classroomUrl' => route('classroom.show', $session),
        'joinable' => $session->canJoinNow(),
        'messagesUrl' => $session->conversation === null ? null : route('messages.show', $session->conversation),
    ])->values()->all();

    // The order's life as a rail: what happened, what is still running and what
    // the teacher is waiting on. Only events the row actually carries appear.
    $timeline = [
        [
            'key' => 'ordered',
            'icon' => 'tag',
            'label' => __('Order placed'),
            'value' => $date($purchase->created_at),
            'tone' => 'primary',
            'done' => true,
            'note' => null,
        ],
        [
            'key' => 'paid',
            'icon' => 'credit-card',
            'label' => $purchase->paid_at === null ? __('Payment') : __('Payment received'),
            'value' => $purchase->paid_at === null
                ? ($isPending ? __('Waiting for the student to pay') : __('Not paid'))
                : $date($purchase->paid_at),
            'tone' => $purchase->paid_at === null ? 'amber' : 'emerald',
            'done' => $purchase->paid_at !== null,
            'note' => null,
        ],
    ];

    if ($purchase->expires_at !== null && $purchase->completed_at === null) {
        $timeline[] = [
            'key' => 'validity',
            'icon' => 'clock',
            'label' => $hasPaid ? __('Usable until') : __('Payment hold until'),
            'value' => $date($purchase->expires_at),
            'tone' => 'slate',
            'done' => false,
            'note' => null,
        ];
    }

    if ($purchase->completed_at !== null) {
        $timeline[] = [
            'key' => 'completed',
            'icon' => 'check-circle',
            'label' => __('Completed'),
            'value' => $date($purchase->completed_at),
            'tone' => 'emerald',
            'done' => true,
            'note' => null,
        ];
    }

    if ($purchase->cancelled_at !== null) {
        $cancelledBy = match ($purchase->cancelled_by) {
            'student' => __('cancelled by the student'),
            'teacher' => __('cancelled by you'),
            'admin' => __('cancelled by support'),
            default => null,
        };

        $timeline[] = [
            'key' => 'cancelled',
            'icon' => 'alert',
            'label' => __('Cancelled'),
            'value' => $date($purchase->cancelled_at),
            'tone' => 'rose',
            'done' => true,
            'note' => collect([$cancelledBy, $purchase->cancellation_reason])->filter()->implode(' — ') ?: null,
        ];
    }

    // The money panel, line by line: what the student actually paid, what the
    // platform keeps and what lands with the teacher.
    $moneyRows = [
        ['key' => 'price', 'label' => __('Service price'), 'value' => $money((int) $purchase->price_minor), 'muted' => false],
    ];

    if ($purchase->netBookingFeeMinor() > 0) {
        $moneyRows[] = [
            'key' => 'booking_fee',
            'label' => $hasPaid ? __('Booking fee paid by the student') : __('Booking fee at checkout'),
            'value' => $money($purchase->netBookingFeeMinor()),
            'muted' => true,
        ];
    }

    $moneyRows[] = [
        'key' => 'total',
        'label' => $hasPaid ? __('Student paid') : __('Order total'),
        'value' => $money($purchase->totalMinor()),
        'muted' => false,
    ];

    $moneyRows[] = [
        'key' => 'commission',
        'label' => __('Platform fee (:percent%)', ['percent' => $purchase->commission_percent]),
        'value' => '−'.$money((int) $purchase->platform_fee_minor),
        'muted' => true,
    ];

    $plan = $isPlan && $allocation > 0 && $purchase->paid_at !== null
        && ! in_array($status, [PurchaseStatus::Cancelled, PurchaseStatus::Expired], true)
        ? [
            'title' => __('Plan balance'),
            'intro' => __(':total minutes split into sessions the way the student likes.', ['total' => $allocation]),
            'remainingTitle' => __('Time left'),
            'remainingValue' => $remaining > 0 ? __(':minutes min', ['minutes' => $remaining]) : __('Done'),
            'remainingTone' => $remaining > 0 ? 'primary' : 'emerald',
            'progress' => [
                'percent' => min(100, (int) round(($allocation - $remaining) * 100 / $allocation)),
                'label' => __(':used of :total min used', ['used' => $allocation - $remaining, 'total' => $allocation]),
            ],
            'usedLabel' => __(':minutes min used', ['minutes' => $allocation - $remaining]),
            'sessionsLabel' => $purchase->max_sessions === null
                ? __(':count sessions booked', ['count' => $countingSessions->count()])
                : __(':count of :max sessions booked', ['count' => $countingSessions->count(), 'max' => (int) $purchase->max_sessions]),
            'sessionsLeftLabel' => $sessionsLeft === null || ! $status->isConsumable()
                ? null
                : ($sessionsLeft > 0
                    ? __(':count sessions left to book', ['count' => $sessionsLeft])
                    : __('Session limit reached')),
            'sessionsLeftTone' => $sessionsLeft !== null && $sessionsLeft > 0 ? 'primary' : 'amber',
        ]
        : null;

    $marking = $purchase->markingOrder === null
        ? null
        : [
            'title' => __('Paper marking'),
            'status' => [
                'label' => $purchase->markingOrder->status->label(),
                'tone' => $markingTone($purchase->markingOrder->status),
            ],
            'body' => match (true) {
                $purchase->markingOrder->status === MarkingOrderStatus::Returned => __('The marked paper is with the student — they can accept it or raise a dispute.'),
                $purchase->markingOrder->status->isFinal() => __('Nothing is left to do on this paper.'),
                $purchase->markingOrder->isOverdue() => __('This paper is past its turnaround deadline — return it as soon as you can.'),
                default => __('Mark the paper and return it within :hours hours of the payment.', [
                    'hours' => $purchase->markingOrder->effectiveTurnaroundHours(),
                ]),
            },
            'deadlineLabel' => $purchase->markingOrder->deadlineAt() === null
                ? null
                : __('Due :when', ['when' => $date($purchase->markingOrder->deadlineAt())]),
            'overdue' => $purchase->markingOrder->isOverdue(),
            'url' => route('teacher.marking.show', $purchase->markingOrder),
            'actionLabel' => $purchase->markingOrder->status->isFinal()
                ? __('Open the marking page')
                : __('Mark this paper'),
        ];

    $offering = $purchase->offering;
    $offeringChips = [];

    if ($isPlan && $allocation > 0) {
        $offeringChips[] = __(':minutes min total', ['minutes' => $allocation]);
    }

    if ($offering?->default_session_minutes) {
        $offeringChips[] = __(':minutes min per session', ['minutes' => (int) $offering->default_session_minutes]);
    }

    if ($purchase->max_sessions !== null) {
        $offeringChips[] = trans_choice(
            'Up to :count session|Up to :count sessions',
            (int) $purchase->max_sessions,
            ['count' => (int) $purchase->max_sessions],
        );
    }

    if ($purchase->turnaround_hours !== null) {
        $offeringChips[] = __(':hours h turnaround', ['hours' => (int) $purchase->turnaround_hours]);
    }

    $config = [
        'appName' => config('app.name', 'Studylikepro'),
        'csrfToken' => csrf_token(),
        'theme' => ['preset' => $themePreset],
        'order' => [
            'id' => $purchase->id,
            'reference' => '#'.str_pad((string) $purchase->id, 5, '0', STR_PAD_LEFT),
            'typeLabel' => $purchase->type()->label(),
            'typeIcon' => $typeIcons[$purchase->type()->value] ?? 'tag',
            'scopeTitle' => $purchase->scopeLabel(),
            'studentName' => $purchase->student->name,
            'studentInitials' => mb_strtoupper(mb_substr($purchase->student->name, 0, 2)),
            'statusLabel' => $status->label(),
            'statusTone' => $purchaseTone($status),
            'priceLabel' => $money((int) $purchase->price_minor),
            'payoutLabel' => $money((int) $purchase->teacher_payout_minor),
            'orderedLabel' => __('Ordered :date', ['date' => $date($purchase->created_at)]),
            'sessionsLabel' => trans_choice(':count session|:count sessions', $sessions->count(), ['count' => $sessions->count()]),
            'sessionsCount' => $sessions->count(),
        ],
        'statusNote' => $isPlan && $status->isConsumable() && $allocation > 0
            ? __(':minutes minutes left for the student.', ['minutes' => $remaining])
            : null,
        'timeline' => $timeline,
        'money' => [
            'rows' => $moneyRows,
            'payout' => [
                'label' => $hasPaid ? __('Your payout') : __('Payout once paid'),
                'value' => $money((int) $purchase->teacher_payout_minor),
            ],
            'payment' => $capturedPayment === null ? null : [
                'label' => __('Paid :when via :gateway', [
                    'when' => $date($capturedPayment->captured_at),
                    'gateway' => ucfirst((string) $capturedPayment->gateway),
                ]),
            ],
            'earning' => $purchase->earning === null ? null : [
                'label' => $purchase->earning->status->label(),
                'tone' => $earningTone($purchase->earning->status),
            ],
        ],
        'plan' => $plan,
        'student' => [
            'name' => $purchase->student->name,
            'initials' => mb_strtoupper(mb_substr($purchase->student->name, 0, 2)),
            'avatarUrl' => $purchase->student->avatarUrl(),
            'gradeLabel' => $purchase->student->studentProfile?->grade?->label,
            'memberSince' => __('Member since :month', [
                'month' => $purchase->student->created_at->copy()->setTimezone($timezone)->format('M Y'),
            ]),
            'messagesUrl' => $conversation === null ? null : route('messages.show', $conversation),
        ],
        'offering' => [
            'title' => $offering?->title ?? $purchase->scopeLabel(),
            'description' => $offering?->description,
            'typeLabel' => $purchase->type()->label(),
            'gradeLabel' => $offering?->grade?->label,
            'subjectLabel' => $purchase->subject?->name ?? $offering?->subject?->name,
            'lessonLabel' => $purchase->lesson?->name ?? $offering?->lesson?->name,
            'chips' => $offeringChips,
            'manageUrl' => route('teacher.services.index'),
        ],
        'sessions' => $sessionRows,
        'marking' => $marking,
        'copy' => [
            'panels' => [
                'timeline' => __('Order timeline'),
                'money' => __('Money'),
                'student' => __('Student'),
                'offering' => __('Service ordered'),
                'sessions' => __('Sessions'),
                'sessionsIntro' => __('Every session the student booked against this order.'),
            ],
            'money' => [
                'feeNote' => __('The platform fee is taken from the service price; the booking fee covers checkout and is not yours to refund.'),
            ],
            'student' => [
                'message' => __('Message student'),
                'noThread' => __('A thread opens once the student books a session.'),
            ],
            'offering' => [
                'manage' => __('Manage in Services and pricing'),
                'grade' => __('Grade'),
                'subject' => __('Subject'),
                'lesson' => __('Lesson'),
                'inactive' => __('This service is no longer published.'),
            ],
            'sessions' => [
                'empty' => __('No sessions booked yet — the student books them from their side.'),
                'open' => __('Open session'),
                'join' => __('Join classroom'),
                'message' => __('Message'),
            ],
            'marking' => [
                'overdue' => __('Overdue'),
            ],
            'back' => __('Back to orders'),
        ],
        'urls' => [
            'index' => route('teacher.orders.index'),
            'services' => route('teacher.services.index'),
        ],
    ];

    // JSON_UNESCAPED_SLASHES keeps the URLs readable; the HEX flags keep the
    // island safe inside a <script> tag.
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('teacher.orders.index') }}" class="text-xs font-semibold text-slate-400 transition-colors hover:text-primary">&larr; {{ __('Orders') }}</a>
            <h2 class="mt-1 font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">
                {{ $purchase->type()->icon() }} {{ $purchase->scopeLabel() }}
            </h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ $purchase->student->name }} · {{ __('your payout') }} {{ $money((int) $purchase->teacher_payout_minor) }}
            </p>
        </div>
    </x-slot>

    <div class="w-full">
        <script type="application/json" id="teacher-order-show-config">@json($config, $jsonFlags)</script>

        <div id="teacher-order-show-app" class="space-y-6"></div>

        {{-- Fallback for a browser without JavaScript: the same status, money,
             plan, sessions and marking facts as a plain document. --}}
        <noscript>
            <div class="space-y-6">
                <div class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Status') }}</p>
                            <p class="mt-1 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $status->label() }}</p>
                            @if ($config['statusNote'])
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $config['statusNote'] }}</p>
                            @endif
                        </div>
                        <div class="text-right">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Your payout') }}</p>
                            <p class="mt-1 text-2xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100">
                                {{ $money((int) $purchase->teacher_payout_minor) }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                    <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('Money') }}</h3>
                    <dl class="mt-3 space-y-2 text-sm">
                        @foreach ($moneyRows as $row)
                            <div class="flex items-center justify-between gap-3">
                                <dt class="text-slate-500 dark:text-slate-400">{{ $row['label'] }}</dt>
                                <dd class="font-semibold {{ $row['muted'] ? 'text-slate-500 dark:text-slate-400' : 'text-slate-800 dark:text-slate-100' }}">{{ $row['value'] }}</dd>
                            </div>
                        @endforeach
                    </dl>
                    @if ($config['money']['payment'])
                        <p class="mt-3 text-xs text-slate-400">{{ $config['money']['payment']['label'] }}</p>
                    @endif
                </div>

                @if ($plan)
                    <div class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ $plan['title'] }}</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $plan['intro'] }}</p>
                        <p class="mt-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
                            {{ $plan['remainingValue'] }} · {{ $plan['progress']['label'] }}
                        </p>
                        <p class="mt-1 text-xs text-slate-400">{{ $plan['sessionsLabel'] }}</p>
                    </div>
                @endif

                @if ($marking)
                    <div class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ $marking['title'] }}</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $marking['body'] }}</p>
                        <p class="mt-2 text-xs font-semibold text-slate-400">{{ $marking['status']['label'] }}</p>
                        <p class="mt-3">
                            <a href="{{ $marking['url'] }}" class="text-sm font-semibold text-primary hover:underline">{{ $marking['actionLabel'] }}</a>
                        </p>
                    </div>
                @endif

                <div class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                    <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('Sessions') }}</h3>
                    @if ($sessionRows === [])
                        <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $config['copy']['sessions']['empty'] }}</p>
                    @else
                        <ul class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($sessionRows as $session)
                                <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                                    <a href="{{ $session['url'] }}" class="flex-1">
                                        <span class="block text-sm font-semibold text-slate-700 dark:text-slate-200">
                                            {{ $session['when'] }} · {{ $session['durationLabel'] }}
                                        </span>
                                        <span class="block text-xs text-slate-400">{{ $session['learnerName'] }}</span>
                                    </a>
                                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">{{ $session['status']['label'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                    <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('Student') }}</h3>
                    <p class="mt-1 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $config['student']['name'] }}</p>
                    @if ($config['student']['gradeLabel'])
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $config['student']['gradeLabel'] }}</p>
                    @endif
                    <p class="mt-1 text-xs text-slate-400">{{ $config['student']['memberSince'] }}</p>
                </div>

                <div class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                    <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('Service ordered') }}</h3>
                    <p class="mt-1 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $config['offering']['title'] }}</p>
                    @if ($config['offering']['description'])
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $config['offering']['description'] }}</p>
                    @endif
                </div>
            </div>
        </noscript>
    </div>

    @vite('resources/js/teacher-order-show/main.js')
</x-app-layout>
