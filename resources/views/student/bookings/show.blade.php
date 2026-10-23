@php
    use App\Enums\BookingStatus;
    use App\Enums\PaymentStatus;

    // The lesson detail page is rendered by resources/js/student-booking-show.
    // Blade keeps ownership of the booking's state — the banners, the timeline,
    // the money, the payment record, the review and the permitted actions — then
    // hands them over as a JSON island.
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);
    $themePreset = auth()->user()->theme['preset'];

    $sessionTone = fn (BookingStatus $status) => match ($status) {
        BookingStatus::PendingPayment => 'amber',
        BookingStatus::InProgress => 'primary',
        BookingStatus::Confirmed, BookingStatus::Completed => 'emerald',
        BookingStatus::NoShow, BookingStatus::Disputed => 'rose',
        BookingStatus::Cancelled, BookingStatus::Expired, BookingStatus::Resolved => 'slate',
    };

    $teacherUser = $booking->teacherProfile->user;
    $isHold = $booking->status === BookingStatus::PendingPayment;
    $holdLapsed = $isHold && $booking->expires_at?->isPast();
    $isCompleted = $booking->status === BookingStatus::Completed;
    $cancelWindowHours = platform_settings()->int('student_cancel_window_hours');

    $startDateLabel = $booking->starts_at->copy()->setTimezone($timezone)->format('D d M Y');
    $startTimeLabel = $booking->starts_at->copy()->setTimezone($timezone)->format('H:i');
    $endTimeLabel = $booking->ends_at->copy()->setTimezone($timezone)->format('H:i');

    // The flash banner a redirect left behind, if any.
    $flash = match (session('status')) {
        'hold-created' => ['tone' => 'amber', 'icon' => 'clock', 'message' => __('Slot reserved. Complete the payment before the hold lapses and the lesson is confirmed.')],
        'session-booked' => ['tone' => 'emerald', 'icon' => 'check-circle', 'message' => __('Session booked — it is already paid for from your purchase.')],
        'booking-confirmed' => ['tone' => 'emerald', 'icon' => 'check-circle', 'message' => __('Payment received — your lesson is confirmed.')],
        'booking-cancelled' => ['tone' => 'slate', 'icon' => null, 'message' => __('Lesson cancelled. The slot is back on the teacher calendar.')],
        'review-submitted' => ['tone' => 'emerald', 'icon' => 'check-circle', 'message' => __('Thank you! Your review is live on the teacher profile.')],
        'review-updated' => ['tone' => 'emerald', 'icon' => 'check-circle', 'message' => __('Review updated — the teacher profile reflects your new rating.')],
        'review-locked' => ['tone' => 'amber', 'icon' => 'alert', 'message' => __('The edit window for this review has closed.')],
        default => null,
    };

    // The classroom callout only exists while the room is open.
    $join = $booking->canJoinNow() ? [
        'title' => __('Your classroom is open'),
        'body' => __('Join :teacher now — the room stays open until :time.', [
            'teacher' => $teacherUser->name,
            'time' => $booking->joinClosesAt()->copy()->setTimezone($timezone)->format('H:i'),
        ]),
        'ctaLabel' => __('Join lesson'),
        'url' => route('classroom.show', $booking),
    ] : null;

    // The one banner that owns the money, decided by the booking's own state.
    $banner = null;

    if ($isHold && ! $holdLapsed) {
        $banner = [
            'kind' => 'hold',
            'title' => __('Your slot is held'),
            'slotLine' => $startDateLabel.', '.$startTimeLabel.'–'.$endTimeLabel.' · '.$booking->durationMinutes().' '.__('minutes'),
            'note' => $booking->expires_at
                ? __('Reserved until :time (:relative). After that the slot is released to other students.', [
                    'time' => $booking->expires_at->copy()->setTimezone($timezone)->format('H:i'),
                    'relative' => $booking->expires_at->diffForHumans(),
                ])
                : __('Complete the payment to confirm this lesson.'),
            // The lesson's share of the total, plus the net booking fee when one applies.
            'priceLine' => collect([
                __('Lesson :price', ['price' => $money($booking->price_minor)]),
                $booking->booking_fee_minor > 0 ? __('booking fee :fee', ['fee' => $money($booking->netBookingFeeMinor())]) : null,
            ])->filter()->implode(' · '),
            'total' => $money($booking->totalMinor()),
            'payLabel' => __('Pay :amount', ['amount' => $money($booking->totalMinor())]),
            'payUrl' => route('student.bookings.checkout', $booking),
            'secureNote' => __('Secure checkout · the slot stays yours until :time', [
                'time' => $booking->expires_at?->copy()->setTimezone($timezone)->format('H:i') ?? __('you pay'),
            ]),
        ];
    } elseif ($holdLapsed || $booking->status === BookingStatus::Expired) {
        $banner = [
            'kind' => 'lapsed',
            'title' => __('This hold lapsed'),
            'body' => __('The payment window closed and the slot went back on the teacher calendar.'),
            'reason' => null,
            'action' => [
                'label' => __('Pick another time'),
                'url' => route('student.bookings.create', $booking->teacher_profile_id),
                'variant' => 'primary',
            ],
        ];
    } elseif ($booking->status === BookingStatus::Cancelled) {
        $banner = [
            'kind' => 'cancelled',
            'title' => __('Lesson cancelled'),
            'body' => __('Cancelled by the :who.', ['who' => $booking->cancelled_by ?? __('platform')]),
            'reason' => $booking->cancellation_reason,
            'action' => [
                'label' => __('Find another teacher'),
                'url' => route('student.teachers.index'),
                'variant' => 'outline',
            ],
        ];
    }

    $details = [
        'dateLine' => $startDateLabel.', '.$startTimeLabel.'–'.$endTimeLabel,
        'durationLine' => $booking->durationMinutes().' '.__('minutes').' · '.$timezone,
        'teacherName' => $teacherUser->name,
        'teacherUrl' => route('student.teachers.show', $booking->teacher_profile_id),
        'subjectLine' => collect([$booking->subject?->name, $booking->lesson?->name])->filter()->implode(' · '),
        'learner' => $booking->learner_name ?? auth()->user()->name,
        'learnerGrade' => $booking->learnerGrade?->label,
        'bookedOn' => $booking->created_at->format('d M Y, H:i'),
        'reference' => '#'.str_pad((string) $booking->id, 6, '0', STR_PAD_LEFT),
    ];

    // Booking::timeline() builds the steps; only the timestamps are converted here.
    $timelineSteps = collect($booking->timeline())
        ->map(fn (array $step) => [
            'key' => $step['key'],
            'label' => $step['label'],
            'at' => $step['at']?->copy()->setTimezone($timezone)->format('d M Y, H:i'),
            'description' => $step['description'],
        ])
        ->values()->all();

    // What the lesson is for. Nothing here is a permission decision — those are
    // the actions below, which Blade has already filtered by policy.
    $moneyBlock = [
        'totalLabel' => $isCompleted ? __('Paid') : __('Lesson total'),
        'total' => $money($booking->totalMinor()),
        'purchaseNotice' => $booking->isPurchaseSession() ? [
            'text' => __('This session is covered by your purchase — no payment is needed.'),
            'linkLabel' => __('View the purchase'),
            'url' => route('student.purchases.show', $booking->service_purchase_id),
        ] : null,
        'breakdown' => $booking->booking_fee_minor > 0 ? [
            [
                'label' => __('Lesson'),
                'tag' => null,
                'value' => $money($booking->price_minor),
                'strike' => null,
                'positive' => false,
            ],
            [
                'label' => __('Platform booking fee'),
                'tag' => $booking->bookingFeePromotion?->name,
                'value' => $money($booking->hasBookingFeeDiscount() ? $booking->netBookingFeeMinor() : $booking->booking_fee_minor),
                'strike' => $booking->hasBookingFeeDiscount() ? $money($booking->booking_fee_minor) : null,
                'positive' => $booking->hasBookingFeeDiscount(),
            ],
        ] : null,
        'payment' => $payment ? [
            'statusLabel' => $payment->status->label(),
            // Payment status colors live in the PHP enum, outside Tailwind's scan.
            'statusTone' => match ($payment->status) {
                PaymentStatus::Created, PaymentStatus::Authorized => 'amber',
                PaymentStatus::Captured => 'emerald',
                PaymentStatus::Failed => 'rose',
                default => 'slate',
            },
            'gatewayId' => $payment->gateway_payment_id,
            'refunded' => $payment->refundedMinor() > 0 ? $money($payment->refundedMinor()) : null,
            'paidOn' => $payment->captured_at?->copy()->setTimezone($timezone)->format('d M Y, H:i') ?? '—',
        ] : null,
        'receiptUrl' => route('receipts.show', $booking),
    ];

    $actions = [];

    if ($booking->status->isLive()) {
        $actions[] = [
            'kind' => 'link',
            'label' => $booking->canJoinNow() ? __('Join lesson') : __('Live classroom'),
            'url' => route('classroom.show', $booking),
            'variant' => $booking->canJoinNow() ? 'primary' : 'outline',
        ];
    }

    // Cancelling opens a dialog instead of navigating; its copy only travels in
    // the island when the policy allows the action at all.
    if ($canCancel) {
        $actions[] = ['kind' => 'dialog', 'label' => __('Cancel lesson'), 'url' => null, 'variant' => 'outline'];
    }

    if ($booking->status === BookingStatus::Confirmed) {
        $actions[] = [
            'kind' => 'form',
            'label' => __('Reschedule'),
            'url' => route('student.bookings.reschedule', $booking),
            'variant' => 'outline',
        ];
    }

    if ($booking->conversation) {
        $actions[] = [
            'kind' => 'link',
            'label' => __('Chat with teacher'),
            'url' => route('messages.show', $booking->conversation),
            'variant' => 'outline',
        ];
    }

    $cancelNotice = ! $canCancel
        && $booking->status === BookingStatus::Confirmed
        && $booking->isInsideStudentCancelWindow($cancelWindowHours)
            ? __('This lesson starts in less than :hours hours, so self-service cancellation is closed — contact support and we will sort it out.', ['hours' => $cancelWindowHours])
            : null;

    $cancelDialog = $canCancel ? [
        'title' => __('Cancel this lesson?'),
        'body' => __('The slot is released immediately. A paid lesson is refunded in full when you cancel outside the :hours-hour window; a teacher cancellation is always refunded.', ['hours' => $cancelWindowHours]),
        'reasonLabel' => __('Reason (optional)'),
        'keepLabel' => __('Keep lesson'),
        'submitLabel' => __('Cancel lesson'),
        'url' => route('student.bookings.cancel', $booking),
        'error' => $errors->first('reason') ?: null,
    ] : null;

    // A session booked against a purchase keeps the rest of the plan in view:
    // the balance, the sibling sessions and the way to spend another one.
    // Non-plan purchases (Q&A, discussion) still list their sessions.
    $planBlock = null;
    $purchase = $booking->servicePurchase;

    if ($purchase !== null) {
        $isPlan = $purchase->isLessonPlan();
        $allocation = (int) $purchase->allocation_minutes;
        $remaining = $isPlan ? $purchase->remainingMinutes() : null;
        $sessionsLeft = $isPlan ? $purchase->sessionsRemaining() : null;
        $canBookSession = $isPlan
            && $remaining > 0
            && ($sessionsLeft === null || $sessionsLeft > 0)
            && \Illuminate\Support\Facades\Gate::allows('scheduleSession', $purchase);

        $planBlock = [
            'title' => $isPlan ? __('Your plan') : __('Your purchase'),
            'balance' => $remaining === null ? null : [
                'label' => __('Time left'),
                'value' => $remaining > 0 ? __(':minutes min', ['minutes' => $remaining]) : __('Done'),
                'percent' => $allocation > 0
                    ? min(100, (int) round(($allocation - $remaining) * 100 / $allocation))
                    : 0,
                'caption' => __(':used of :total min used', ['used' => $allocation - $remaining, 'total' => $allocation]),
            ],
            'sessionsLeft' => $sessionsLeft === null
                ? null
                : trans_choice(':count session left to book|:count sessions left to book', $sessionsLeft, ['count' => $sessionsLeft]),
            'sessionsTitle' => __('Sessions on this purchase'),
            'currentLabel' => __('This session'),
            'sessions' => $purchase->sessions->map(fn ($session) => [
                'id' => $session->id,
                'url' => route('student.bookings.show', $session),
                'when' => $session->starts_at->copy()->setTimezone($timezone)->format('D, d M Y · H:i'),
                'durationLabel' => __(':minutes min', ['minutes' => $session->durationMinutes()]),
                'scope' => collect([$session->subject?->name, $session->lesson?->name])->filter()->implode(' · ') ?: null,
                'status' => [
                    'label' => $session->status->label(),
                    'tone' => $sessionTone($session->status),
                ],
                'current' => $session->is($booking),
            ])->values()->all(),
            'exhausted' => $isPlan && ! $canBookSession && $remaining > 0 && $sessionsLeft === 0,
            'limitLabel' => __('This plan has reached its session limit.'),
            'bookUrl' => $canBookSession ? route('student.purchase-sessions.create', $purchase) : null,
            'bookLabel' => __('Book another session'),
            'purchaseUrl' => route('student.purchases.show', $purchase),
            'purchaseLabel' => __('View purchase'),
        ];
    }

    $reviewBlock = null;

    if ($isCompleted) {
        $review = $booking->review;
        $editable = (bool) $review?->isEditable();

        $reviewBlock = [
            'heading' => __('Your review'),
            'hasReview' => $review !== null,
            // The label only travels when it applies: the student either has a
            // verdict to write or one to edit.
            'actionLabel' => $review === null
                ? __('Leave a review')
                : ($editable ? __('Edit review') : null),
            'actionUrl' => route('student.reviews.show', $booking),
            'prompt' => $review === null
                ? __('How did it go? A review takes a minute and helps other students pick the right teacher.')
                : null,
            'rating' => $review?->rating,
            'outOfFive' => $review ? $review->rating.'/5' : null,
            'comment' => $review?->comment,
            'noComment' => __('No comment left.'),
            'metaLine' => $review ? collect([
                __('Published :when', ['when' => $review->created_at->diffForHumans()]),
                $review->wasEdited() ? __('edited :when', ['when' => $review->edited_at->diffForHumans()]) : null,
                $editable ? null : __('edit window closed'),
            ])->filter()->implode(' · ') : null,
            'editableUntil' => $editable
                ? __('You can change this review until :date.', ['date' => $review->editableUntil()->format('d M Y')])
                : null,
        ];
    }

    $bookingPayload = [
        'appName' => config('app.name', 'Studylikepro'),
        'csrfToken' => csrf_token(),
        'theme' => ['preset' => $themePreset],
        'flash' => $flash,
        'error' => $errors->first('status') ?: null,
        'join' => $join,
        'banner' => $banner,
        'details' => $details,
        'timeline' => [
            'steps' => $timelineSteps,
            'cancellationReason' => $booking->cancellation_reason,
        ],
        'money' => $moneyBlock,
        'actions' => $actions,
        'cancelNotice' => $cancelNotice,
        'cancel' => $cancelDialog,
        'review' => $reviewBlock,
        'plan' => $planBlock,
        'copy' => [
            'lessonLabel' => __('Lesson'),
            'teacherLabel' => __('Teacher'),
            'learnerLabel' => __('Learner'),
            'bookedOnLabel' => __('Booked on'),
            'referenceLabel' => __('Reference'),
            'timelineTitle' => __('Status timeline'),
            'pendingLabel' => __('pending'),
            'paymentLabel' => __('Payment'),
            'refundedLabel' => __('Refunded'),
            'paidOnLabel' => __('Paid on'),
            'receiptLabel' => __('View receipt'),
        ],
        'urls' => [
            'index' => route('student.bookings.index'),
        ],
    ];

    // JSON_UNESCAPED_SLASHES keeps the URLs readable; the HEX flags keep the
    // island safe inside a <script> tag.
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                {{-- Walks back through history when the student came from another page of ours; the href covers a cold load, a missing referrer or a return to this same lesson. --}}
                <a href="{{ route('student.bookings.index') }}"
                   onclick="const from = document.referrer; if (window.history.length > 1 && from && from !== window.location.href && from.startsWith(window.location.origin)) { window.history.back(); return false; }"
                   class="text-xs font-semibold text-slate-400 transition-colors hover:text-primary">&larr; {{ __('Back') }}</a>
                <h2 class="mt-1 font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">
                    {{ $booking->lesson?->name ?? $booking->subject?->name ?? __('Lesson') }}
                </h2>
            </div>
            <x-booking-status-badge :status="$booking->status" class="px-3 py-1 text-xs" />
        </div>
    </x-slot>

    <script type="application/json" id="student-booking-show-config">@json($bookingPayload, $jsonFlags)</script>

    <div id="student-booking-show-app" class="w-full"></div>

    {{-- Fallback for a browser without JavaScript: the page is a thin shell, so
         print the same state, total and actions the Vue app would render. --}}
    <noscript>
        <div class="w-full space-y-6">
            @if ($flash)
                <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-300">
                    {{ $flash['message'] }}
                </div>
            @endif

            <div class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Lesson') }}</p>
                <p class="mt-1 text-lg font-bold text-slate-800 dark:text-slate-100">{{ $details['dateLine'] }}</p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $details['durationLine'] }}</p>
                <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                    {{ $details['teacherName'] }}@if ($details['subjectLine']) · {{ $details['subjectLine'] }}@endif
                </p>
                <p class="mt-3 text-sm font-semibold text-slate-700 dark:text-slate-200">
                    {{ $moneyBlock['totalLabel'] }} {{ $moneyBlock['total'] }}
                </p>
            </div>

            @if ($banner)
                <div class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ $banner['title'] }}</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $banner['body'] ?? $banner['note'] }}</p>

                    @if ($banner['kind'] === 'hold')
                        <form method="POST" action="{{ $banner['payUrl'] }}" class="mt-3">
                            @csrf
                            <button type="submit" class="inline-flex items-center rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white">{{ $banner['payLabel'] }}</button>
                        </form>
                    @elseif ($banner['action'])
                        <a href="{{ $banner['action']['url'] }}" class="mt-3 inline-block font-semibold text-primary hover:underline">{{ $banner['action']['label'] }}</a>
                    @endif
                </div>
            @endif

            @if ($join)
                <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-6 dark:border-emerald-900/40 dark:bg-emerald-950/20">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ $join['title'] }}</h3>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $join['body'] }}</p>
                    <a href="{{ $join['url'] }}" class="mt-3 inline-block rounded-xl bg-primary px-4 py-2 text-sm font-bold text-white">{{ $join['ctaLabel'] }}</a>
                </div>
            @endif

            @if ($planBlock)
                <div class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ $planBlock['title'] }}</h3>

                    @if ($planBlock['balance'])
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            {{ $planBlock['balance']['label'] }}: <span class="font-semibold text-slate-700 dark:text-slate-200">{{ $planBlock['balance']['value'] }}</span>
                            · {{ $planBlock['balance']['caption'] }}
                        </p>
                    @endif

                    <ul class="mt-4 space-y-2">
                        @foreach ($planBlock['sessions'] as $session)
                            <li class="text-sm text-slate-600 dark:text-slate-300">
                                <a href="{{ $session['url'] }}" class="font-semibold hover:text-primary">{{ $session['when'] }}</a>
                                · {{ $session['durationLabel'] }} · {{ $session['status']['label'] }}
                                @if ($session['current'])
                                    <span class="text-xs font-semibold text-primary">({{ $planBlock['currentLabel'] }})</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>

                    @if ($planBlock['bookUrl'])
                        <a href="{{ $planBlock['bookUrl'] }}" class="mt-4 inline-block rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white">{{ $planBlock['bookLabel'] }}</a>
                    @elseif ($planBlock['exhausted'])
                        <p class="mt-4 text-sm text-amber-700 dark:text-amber-400">{{ $planBlock['limitLabel'] }}</p>
                    @endif

                    <a href="{{ $planBlock['purchaseUrl'] }}" class="mt-3 block font-semibold text-primary hover:underline">{{ $planBlock['purchaseLabel'] }}</a>
                </div>
            @endif

            <div class="flex flex-wrap items-center gap-3">
                @foreach ($actions as $action)
                    @if ($action['kind'] === 'link')
                        <a href="{{ $action['url'] }}" class="font-semibold text-primary hover:underline">{{ $action['label'] }}</a>
                    @elseif ($action['kind'] === 'form')
                        <form method="POST" action="{{ $action['url'] }}">
                            @csrf
                            <button type="submit" class="font-semibold text-primary hover:underline">{{ $action['label'] }}</button>
                        </form>
                    @elseif ($cancelDialog)
                        <form method="POST" action="{{ $cancelDialog['url'] }}">
                            @csrf
                            <button type="submit" class="font-semibold text-rose-600 hover:underline">{{ $cancelDialog['submitLabel'] }}</button>
                        </form>
                    @endif
                @endforeach

                @if ($reviewBlock && ! $reviewBlock['hasReview'])
                    <a href="{{ $reviewBlock['actionUrl'] }}" class="font-semibold text-primary hover:underline">{{ $reviewBlock['actionLabel'] }}</a>
                @endif
            </div>
        </div>
    </noscript>

    @vite('resources/js/student-booking-show/main.js')
</x-app-layout>