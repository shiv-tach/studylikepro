@php
    use App\Enums\BookingStatus;
    use App\Enums\MarkingOrderStatus;
    use App\Enums\PurchaseStatus;
    use App\Enums\ServiceType;
    use Illuminate\Support\Facades\Gate;

    // One purchase in detail is rendered by resources/js/student-purchase-show.
    // Blade keeps every permission check, price, date and translated string, then
    // hands them over as a JSON island, so no URL, figure or wording is ever
    // hardcoded in the Vue app.
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);
    $timezone = config('studylikepro.default_display_timezone');
    $isPlan = $purchase->isLessonPlan();

    // Statuses carry their own Tailwind classes in the PHP enums, which sit
    // outside Tailwind's scanned paths, so the island carries a semantic chip
    // tone instead and the Vue components own the colours.
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

    // A hold whose payment window has closed is already lost even though the
    // scheduler has not written the status yet — the same reading the wallet list
    // uses, so this page never offers a payment the gateway would refuse.
    $status = $purchase->isHoldLapsed() ? PurchaseStatus::Expired : $purchase->status;
    $isPending = $status === PurchaseStatus::PendingPayment;

    // Only a lesson plan carries a balance, so the remaining minutes are read for
    // exactly the panel that displays them.
    $allocation = (int) $purchase->allocation_minutes;
    $remaining = $isPlan ? $purchase->remainingMinutes() : null;

    // A plan is spent either by minutes or by a session cap, and the cap takes
    // the choice away first, so the panel has to know both.
    $sessionsLeft = $isPlan && $purchase->max_sessions !== null ? $purchase->sessionsRemaining() : null;
    $canBookSession = $remaining !== null
        && $remaining > 0
        && ($sessionsLeft === null || $sessionsLeft > 0)
        && Gate::allows('scheduleSession', $purchase);

    // What the student may still do with this purchase, decided here so the Vue
    // app never re-derives a permission.
    $actions = [];

    if ($isPending) {
        $actions[] = [
            'kind' => 'form',
            'url' => route('student.purchases.checkout', $purchase),
            'label' => __('Pay now'),
            'amount' => $money($purchase->totalMinor()),
            'variant' => 'primary',
        ];
        $actions[] = [
            'kind' => 'form',
            'url' => route('student.purchases.cancel', $purchase),
            'label' => __('Cancel purchase'),
            'variant' => 'outline',
        ];
    }

    // One banner per redirect this page can be reached with, so it explains what
    // just happened without needing a URL of its own.
    $flash = match (session('status')) {
        'purchase-created' => [
            'tone' => 'primary',
            'icon' => 'sparkles',
            'message' => __('Your purchase is ready — pay now to activate it.'),
        ],
        'purchase-paid' => [
            'tone' => 'emerald',
            'icon' => 'check-circle',
            'message' => __('Payment received — your purchase is now active.'),
        ],
        'payment-pending' => [
            'tone' => 'amber',
            'icon' => 'clock',
            'message' => __('We are still waiting for the payment to reach us. Refresh in a moment — nothing has been charged twice.'),
        ],
        'purchase-cancelled' => [
            'tone' => 'slate',
            'icon' => 'folder',
            'message' => __('The purchase was cancelled.'),
        ],
        'session-booked' => [
            'tone' => 'emerald',
            'icon' => 'calendar',
            'message' => __('Session booked — it is already paid for from your plan.'),
        ],
        'session-cancelled' => [
            'tone' => 'slate',
            'icon' => 'calendar',
            'message' => __('The session was cancelled and its time returned to your plan.'),
        ],
        default => null,
    };

    // An active lesson plan is the one purchase that still has something to spend.
    $plan = $isPlan && $status === PurchaseStatus::Active && $remaining !== null
        ? [
            'title' => __('Your plan'),
            'intro' => __(':total minutes split into sessions the way you like.', ['total' => $allocation]),
            'remainingTitle' => __('Time left'),
            'remainingValue' => $remaining > 0 ? __(':minutes min', ['minutes' => $remaining]) : __('Done'),
            'remainingTone' => $remaining > 0 ? 'primary' : 'emerald',
            'progress' => [
                'percent' => $allocation > 0
                    ? min(100, (int) round(($allocation - $remaining) * 100 / $allocation))
                    : 0,
                'label' => __(':used of :total min used', ['used' => $allocation - $remaining, 'total' => $allocation]),
            ],
            'exhausted' => $remaining > 0 && $sessionsLeft === 0,
            'limitLabel' => __('This plan has reached its session limit.'),
            'bookUrl' => $canBookSession ? route('student.purchase-sessions.create', $purchase) : null,
            'bookLabel' => __('Book a session'),
        ]
        : null;

    // A live hold is the only panel racing a clock, so it carries its deadline as
    // an instant and the amount it would charge, and the countdown is filled in
    // by the component instead of a pre-rendered sentence.
    $hold = $isPending && $purchase->expires_at?->isFuture()
        ? ['deadline' => $purchase->expires_at->toIso8601String(), 'amount' => $money($purchase->totalMinor())]
        : null;

    // Whatever the teacher owes this purchase: a marked paper, linking to the
    // screen that tracks its delivery.
    $delivery = match (true) {
        $purchase->type() === ServiceType::PaperMarking && $purchase->markingOrder !== null => [
            'icon' => 'clipboard',
            'title' => __('Paper marking'),
            'body' => match (true) {
                $purchase->markingOrder->status === MarkingOrderStatus::Returned => __('Your marked paper is ready — open it to accept or dispute.'),
                $purchase->markingOrder->isOverdue() => __('Past the turnaround deadline — contact support if nothing arrives soon.'),
                default => __('Being marked — usually back within :hours hours.', [
                    'hours' => $purchase->markingOrder->turnaround_hours
                        ?? config('studylikepro.services.marking_default_turnaround_hours'),
                ]),
            },
            'badge' => [
                'label' => $purchase->markingOrder->status->label(),
                'tone' => $markingTone($purchase->markingOrder->status),
            ],
            'url' => route('student.marking-orders.show', $purchase->markingOrder),
        ],
        default => null,
    };

    // The sessions this purchase paid for, formatted here so the component only
    // has to lay them out.
    $sessions = $purchase->sessions->map(fn ($session) => [
        'id' => $session->id,
        'url' => route('student.bookings.show', $session),
        'when' => $session->starts_at->copy()->setTimezone($timezone)->format('D, d M Y · H:i'),
        'durationLabel' => __(':minutes min', ['minutes' => $session->durationMinutes()]),
        // The scope line is dropped when there is nothing to name it by.
        'scope' => collect([$session->subject?->name, $session->lesson?->name])->filter()->implode(' · ') ?: null,
        'status' => [
            'label' => $session->status->label(),
            'tone' => $sessionTone($session->status),
        ],
    ])->values()->all();

    $purchasePayload = [
        'appName' => config('app.name', 'Studylikepro'),
        'csrfToken' => csrf_token(),
        'theme' => ['preset' => auth()->user()->theme['preset']],
        'flash' => $flash,
        'error' => $errors->first('status') ?: null,
        'status' => [
            'value' => $status->value,
            'label' => $status->label(),
            'tone' => $purchaseTone($status),
            // The window is labelled by what it is: a payment deadline while the
            // money is outstanding, a validity date once the purchase is live.
            'expiry' => $purchase->expires_at === null ? null : [
                'label' => $isPending ? __('Payment hold until') : __('Valid until'),
                'value' => $purchase->expires_at->copy()->setTimezone($timezone)->format('d M Y, H:i'),
            ],
            'price' => [
                'label' => __('Total price'),
                'value' => $money($purchase->price_minor),
                'fee' => $purchase->booking_fee_minor > 0
                    ? __('includes :fee platform fee', ['fee' => $money($purchase->netBookingFeeMinor())])
                    : null,
            ],
            'payment' => $capturedPayment === null ? null : [
                'label' => __('Paid :when via :gateway', [
                    'when' => $capturedPayment->captured_at?->setTimezone($timezone)->format('d M Y, H:i'),
                    'gateway' => $capturedPayment->gateway,
                ]),
            ],
            'hold' => $hold,
            'actions' => $actions,
            // What the panel becomes if the countdown reaches zero while the page
            // stays open, so it never keeps offering a payment that lapsed.
            'expired' => [
                'value' => PurchaseStatus::Expired->value,
                'label' => PurchaseStatus::Expired->label(),
            ],
        ],
        'plan' => $plan,
        'delivery' => $delivery,
        'sessions' => $sessions === [] ? null : [
            'title' => __('Sessions'),
            'items' => $sessions,
        ],
        'copy' => [
            'statusLabel' => __('Status'),
            // The countdown fills :time in itself, so the ticking seconds are
            // never rendered — or rounded — by Blade.
            'payWithin' => __('Pay within :time', ['time' => ':time']),
            'holdExpired' => __('Hold expired'),
            'lapsed' => __('Nothing was charged — refresh to see the latest state.'),
            'refresh' => __('Refresh'),
        ],
        'urls' => [
            'index' => route('student.purchases.index'),
        ],
    ];

    // JSON_UNESCAPED_SLASHES keeps the URLs readable; the HEX flags keep the
    // island safe inside a <script> tag.
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('student.purchases.index') }}" class="text-xs font-semibold text-slate-400 transition-colors hover:text-primary">&larr; {{ __('My purchases') }}</a>
            <h2 class="mt-1 font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">
                {{ $purchase->type()->icon() }} {{ $purchase->scopeLabel() }}
            </h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ $purchase->type()->label() }} · {{ $purchase->teacherProfile->user->name }}
            </p>
        </div>
    </x-slot>

    <div class="w-full">
        <script type="application/json" id="student-purchase-show-config">@json($purchasePayload, $jsonFlags)</script>

        <div id="student-purchase-show-app" class="space-y-6"></div>

        {{-- Fallback for a browser without JavaScript: the page is a thin shell,
             so print the same purchase the Vue app would have rendered. --}}
        <noscript>
            <div class="space-y-6">
                @if ($flash)
                    <div class="rounded-2xl border p-4 text-sm {{ match ($flash['tone']) {
                        'primary' => 'border-primary/30 bg-primary/5 text-primary',
                        'emerald' => 'border-emerald-200/80 bg-emerald-50 text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300',
                        'amber' => 'border-amber-200/80 bg-amber-50 text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300',
                        default => 'border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300',
                    } }}">
                        {{ $flash['message'] }}
                    </div>
                @endif

                @if ($errors->has('status'))
                    <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
                        {{ $errors->first('status') }}
                    </div>
                @endif

                <div class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Status') }}</p>
                            <p class="mt-1 text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $status->label() }}</p>
                            @if ($purchasePayload['status']['expiry'])
                                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                    {{ $purchasePayload['status']['expiry']['label'] }} {{ $purchasePayload['status']['expiry']['value'] }}
                                </p>
                            @endif
                        </div>
                        <div class="text-right">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Total price') }}</p>
                            <p class="mt-1 text-2xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100">
                                {{ $money($purchase->price_minor) }}
                            </p>
                            @if ($purchasePayload['status']['price']['fee'])
                                <p class="mt-1 text-xs text-slate-400">{{ $purchasePayload['status']['price']['fee'] }}</p>
                            @endif
                        </div>
                    </div>

                    @if ($actions !== [])
                        <div class="mt-5 flex flex-wrap gap-3 border-t border-slate-200 pt-5 dark:border-slate-800">
                            @foreach ($actions as $action)
                                <form method="POST" action="{{ $action['url'] }}">
                                    @csrf
                                    <button type="submit"
                                            class="inline-flex items-center rounded-xl px-4 py-2 text-sm font-semibold transition-colors {{ $action['variant'] === 'primary'
                                                ? 'bg-primary text-white shadow-sm hover:bg-primary/90'
                                                : 'border border-slate-300 text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                                        {{ $action['label'] }}@if (! empty($action['amount'])) — {{ $action['amount'] }}@endif
                                    </button>
                                </form>
                            @endforeach
                        </div>
                    @endif

                    @if ($purchasePayload['status']['payment'])
                        <p class="mt-4 text-xs text-slate-400">{{ $purchasePayload['status']['payment']['label'] }}</p>
                    @endif
                </div>

                @if ($plan)
                    <div class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ $plan['title'] }}</h3>
                                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $plan['intro'] }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $plan['remainingTitle'] }}</p>
                                <p class="mt-1 text-2xl font-extrabold tracking-tight {{ $plan['remainingTone'] === 'primary' ? 'text-primary' : 'text-emerald-600 dark:text-emerald-400' }}">{{ $plan['remainingValue'] }}</p>
                            </div>
                        </div>

                        <div class="mt-4 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                            <div class="h-full rounded-full {{ $plan['remainingTone'] === 'primary' ? 'bg-primary' : 'bg-emerald-500' }}" style="width: {{ $plan['progress']['percent'] }}%"></div>
                        </div>

                        @if ($plan['exhausted'])
                            <p class="mt-5 text-sm text-amber-700 dark:text-amber-400">{{ $plan['limitLabel'] }}</p>
                        @elseif ($plan['bookUrl'])
                            <p class="mt-5">
                                <a href="{{ $plan['bookUrl'] }}"
                                   class="inline-flex items-center rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary/90">
                                    {{ $plan['bookLabel'] }}
                                </a>
                            </p>
                        @endif
                    </div>
                @endif

                @if ($delivery)
                    <p>
                        <a href="{{ $delivery['url'] }}"
                           class="block rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                            <strong class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ $delivery['title'] }}</strong>
                            <span class="mt-1 block text-sm text-slate-500 dark:text-slate-400">{{ $delivery['body'] }}</span>
                            <span class="mt-2 block text-xs font-semibold text-slate-400">{{ $delivery['badge']['label'] }}</span>
                        </a>
                    </p>
                @endif

                @if ($sessions !== [])
                    <div class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('Sessions') }}</h3>
                        <ul class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                            @foreach ($sessions as $session)
                                <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                                    <a href="{{ $session['url'] }}" class="flex-1">
                                        <span class="block text-sm font-semibold text-slate-700 dark:text-slate-200">
                                            {{ $session['when'] }} · {{ $session['durationLabel'] }}
                                        </span>
                                        @if ($session['scope'])
                                            <span class="block text-xs text-slate-400">{{ $session['scope'] }}</span>
                                        @endif
                                    </a>
                                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">{{ $session['status']['label'] }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </noscript>
    </div>

    @vite('resources/js/student-purchase-show/main.js')
</x-app-layout>
