@php
    use App\Enums\BookingStatus;
    use App\Enums\MarkingOrderStatus;
    use App\Enums\PurchaseStatus;
    use App\Enums\ServiceType;

    // /teacher/orders is rendered by resources/js/teacher-orders. Blade owns the
    // query results, every status count, the money labels, the filter and search
    // URLs and the translated strings, then hands them over as a JSON island. The
    // <noscript> block below repeats the same list without JavaScript, and the
    // paginator stays Blade-rendered because its links are plain URLs.
    $themePreset = $userTheme['preset'] ?? 'classic';
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);
    $timezone = config('studylikepro.default_display_timezone');

    // Statuses carry Tailwind classes in the PHP enums, which sit outside
    // Tailwind's scanned paths, so the island carries a semantic chip tone and the
    // Vue components own the colours.
    $purchaseTone = fn (PurchaseStatus $status) => match ($status) {
        PurchaseStatus::PendingPayment => 'amber',
        PurchaseStatus::Active => 'primary',
        PurchaseStatus::Completed => 'emerald',
        PurchaseStatus::Disputed => 'rose',
        PurchaseStatus::Cancelled, PurchaseStatus::Expired, PurchaseStatus::Resolved => 'slate',
    };

    $markingTone = fn (MarkingOrderStatus $status) => match ($status) {
        MarkingOrderStatus::Submitted => 'amber',
        MarkingOrderStatus::InProgress => 'primary',
        MarkingOrderStatus::Returned => 'sky',
        MarkingOrderStatus::Accepted => 'emerald',
        MarkingOrderStatus::Disputed => 'rose',
        MarkingOrderStatus::Resolved, MarkingOrderStatus::Cancelled => 'slate',
    };

    // The shared icon set (resources/js/shared/icons.js) names each service.
    $typeIcons = [
        ServiceType::Lesson->value => 'graduation',
        ServiceType::PaperMarking->value => 'clipboard',
        ServiceType::PaperDiscussion->value => 'chat',
        ServiceType::Qna->value => 'sparkles',
    ];

    // Whatever is in the query string that is not a page, a status or a search
    // survives every link below, so switching one filter keeps the rest.
    $carried = collect(request()->query())->except(['page', 'status', 'search'])->all();
    $indexUrl = fn (array $params = []) => route('teacher.orders.index', array_filter(
        [...$carried, 'status' => $selectedStatus, 'search' => $search !== '' ? $search : null, ...$params],
        fn ($value) => $value !== null && $value !== '',
    ));

    // Every status is offered, never only the ones already in use: an empty tab
    // is exactly what tells the teacher there is nothing at that stage yet.
    $statusTabs = collect([null, ...$statuses])->map(fn (?PurchaseStatus $status) => [
        'key' => $status?->value ?? '',
        'label' => $status?->label() ?? __('All orders'),
        'count' => $status === null
            ? (int) $counts['total']
            : (int) ($statusCounts[$status->value] ?? 0),
        'url' => $indexUrl(['status' => $status?->value]),
        'active' => ($status?->value ?? '') === ($selectedStatus ?? ''),
    ])->values()->all();

    $cards = collect($purchases->items())->map(function ($purchase) use (
        $markingTone,
        $money,
        $purchaseTone,
        $timezone,
        $typeIcons
    ) {
        // A hold whose window has closed is already lost even though the
        // scheduler has not written the status yet, so the card reads it as
        // expired rather than as a payable order.
        $status = $purchase->isHoldLapsed() ? PurchaseStatus::Expired : $purchase->status;
        $sessions = $purchase->sessions;
        $counting = $sessions->whereNotIn('status', [BookingStatus::Cancelled, BookingStatus::Expired]);

        $chips = [];

        if ($sessions->isNotEmpty()) {
            $chips[] = [
                'icon' => 'calendar',
                'label' => trans_choice(':count session|:count sessions', $sessions->count(), ['count' => $sessions->count()]),
                'tone' => 'slate',
            ];
        }

        // Only a paid lesson plan carries a balance worth reading here: an unpaid
        // hold has not bought any minutes yet.
        if ($purchase->isLessonPlan() && $purchase->allocation_minutes && $purchase->paid_at !== null
            && ! in_array($status, [PurchaseStatus::Cancelled, PurchaseStatus::Expired], true)) {
            $remaining = max(0, (int) $purchase->allocation_minutes - $counting->sum(fn ($session) => $session->durationMinutes()));

            $chips[] = [
                'icon' => 'clock',
                'label' => $remaining > 0
                    ? __(':minutes min left', ['minutes' => $remaining])
                    : __('Fully booked'),
                'tone' => $remaining > 0 ? 'primary' : 'emerald',
            ];
        }

        // What the teacher still owes: a paper waiting to be marked, or one that
        // has been returned and is waiting on the student.
        if ($purchase->type() === ServiceType::PaperMarking && $purchase->markingOrder !== null) {
            $chips[] = [
                'icon' => 'clipboard',
                'label' => $purchase->markingOrder->status->label(),
                'tone' => $markingTone($purchase->markingOrder->status),
            ];
        }

        // The next session is the most actionable thing on a paid order, so it
        // gets its own line instead of a fourth chip.
        $next = $sessions->first(fn ($session) => $session->status->holdsSlot() && $session->starts_at->isFuture());

        return [
            'id' => $purchase->id,
            'reference' => '#'.str_pad((string) $purchase->id, 5, '0', STR_PAD_LEFT),
            'url' => route('teacher.orders.show', $purchase),
            'typeLabel' => $purchase->type()->label(),
            'typeIcon' => $typeIcons[$purchase->type()->value] ?? 'tag',
            'statusValue' => $status->value,
            'statusLabel' => $status->label(),
            'statusTone' => $purchaseTone($status),
            'scopeTitle' => $purchase->scopeLabel(),
            'studentName' => $purchase->student->name,
            'studentInitials' => mb_strtoupper(mb_substr($purchase->student->name, 0, 2)),
            'orderedLabel' => __('Ordered :date', ['date' => $purchase->created_at->copy()->setTimezone($timezone)->format('d M Y')]),
            'priceLabel' => $money((int) $purchase->price_minor),
            'payoutLabel' => $money((int) $purchase->teacher_payout_minor),
            'payoutCaption' => $purchase->paid_at === null ? __('Payout if paid') : __('Your payout'),
            // Only a live service expects sessions; a marked paper does not, so
            // the card must not report "nothing booked" for it.
            'isLiveService' => $purchase->type()->isLiveSession(),
            'sessionCount' => $sessions->count(),
            'chips' => $chips,
            'nextSession' => $next === null ? null : [
                'label' => __('Next session'),
                'value' => display_datetime($next->starts_at->copy()->setTimezone($timezone), 'D, d M Y', ' · '),
            ],
        ];
    })->values()->all();

    $config = [
        'appName' => config('app.name', 'Studylikepro'),
        'csrfToken' => csrf_token(),
        'theme' => ['preset' => $themePreset],
        'stats' => [
            'total' => (int) $counts['total'],
            'awaiting' => (int) $counts['awaiting'],
            'active' => (int) $counts['active'],
            'completed' => (int) $counts['completed'],
            'earnedLabel' => $money((int) $counts['earnedMinor']),
        ],
        'statusTabs' => $statusTabs,
        'search' => [
            'value' => $search,
            'label' => __('Search orders'),
            'placeholder' => __('Student name, service or order number'),
            'submit' => __('Search'),
            'clear' => __('Clear search'),
            'clearUrl' => $indexUrl(['search' => null]),
            'status' => $selectedStatus ?? '',
        ],
        'purchases' => $cards,
        'pagination' => [
            'visible' => $purchases->count(),
            'total' => $purchases->total(),
        ],
        'filtered' => $selectedStatus !== null || $search !== '',
        'copy' => [
            'hero' => [
                'eyebrow' => __('Your orders'),
                'title' => __('Everything students have ordered'),
                'intro' => __('Every paid service, what is still owed on it and where its sessions stand — one place to keep your side of the work moving.'),
                'earnedLabel' => __('Earned so far'),
            ],
            'stats' => [
                'label' => __('Order totals'),
                'total' => __(':count order|:count orders'),
                'awaiting' => __('Awaiting payment'),
                'active' => __('Active'),
                'completed' => __('Completed'),
            ],
            'tabs' => [
                'label' => __('Filter by order status'),
            ],
            'toolbar' => [
                'searchLabel' => __('Search orders'),
                'viewLabel' => __('Order layout'),
                'viewList' => __('List view'),
                'viewCard' => __('Card view'),
            ],
            'results' => [
                'label' => __(':count order|:count orders'),
                'filteredLabel' => __(':count matching order|:count matching orders'),
                'showing' => __('Showing :visible of :total orders'),
            ],
            'card' => [
                'payout' => __('Your payout'),
                'price' => __('Service price'),
                'open' => __('Open order'),
                'noSessions' => __('No sessions booked yet'),
                'noUpcoming' => __('No upcoming session'),
            ],
            'empty' => [
                'title' => __('No orders yet'),
                'body' => __('Publish services from the Services and pricing page and the first order lands here as soon as a student pays.'),
                'cta' => __('Go to Services and pricing'),
                'filteredTitle' => __('Nothing matches this view'),
                'filteredBody' => __('Try another status, or clear the search to see every order again.'),
                'filteredCta' => __('Clear filters'),
            ],
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
            <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Orders') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Everything students have ordered from your service catalog.') }}</p>
        </div>
    </x-slot>

    <div class="w-full">
        <script type="application/json" id="teacher-orders-config">@json($config, $jsonFlags)</script>

        <div id="teacher-orders-app" class="space-y-6"></div>

        {{-- Fallback for a browser without JavaScript: the same filters as plain
             links, the same search as a GET form and the same orders as a list. --}}
        <noscript>
            <div class="space-y-6">
                <nav aria-label="{{ $config['copy']['tabs']['label'] }}" class="flex flex-wrap gap-2">
                    @foreach ($statusTabs as $tab)
                        <a href="{{ $tab['url'] }}"
                           @if ($tab['active']) aria-current="page" @endif
                           class="rounded-xl px-4 py-2 text-sm font-semibold {{ $tab['active']
                                ? 'bg-primary text-white shadow-sm'
                                : 'border border-slate-200 text-slate-600 dark:border-slate-800 dark:text-slate-300' }}">
                            {{ $tab['label'] }} ({{ $tab['count'] }})
                        </a>
                    @endforeach
                </nav>

                <form method="GET" action="{{ $config['urls']['index'] }}" role="search" class="flex flex-wrap items-center gap-2">
                    @if ($selectedStatus)
                        <input type="hidden" name="status" value="{{ $selectedStatus }}">
                    @endif
                    <label for="teacher-orders-search-noscript" class="sr-only">{{ $config['search']['label'] }}</label>
                    <input id="teacher-orders-search-noscript" type="search" name="search" value="{{ $search }}"
                           placeholder="{{ $config['search']['placeholder'] }}"
                           class="rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    <button type="submit"
                            class="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary/90">
                        {{ $config['search']['submit'] }}
                    </button>
                </form>

                @forelse ($purchases as $purchase)
                    <div class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                        <p class="text-[11px] font-semibold uppercase tracking-wider text-primary">
                            {{ $purchase->type()->icon() }} {{ $purchase->type()->label() }}
                            · {{ $purchase->status->label() }}
                        </p>
                        <h3 class="mt-1 text-sm font-bold text-slate-800 dark:text-slate-100">
                            <a class="hover:underline" href="{{ route('teacher.orders.show', $purchase) }}">{{ $purchase->scopeLabel() }}</a>
                        </h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            {{ $purchase->student->name }} ·
                            {{ __('your payout') }} {{ $money((int) $purchase->teacher_payout_minor) }}
                        </p>
                    </div>
                @empty
                    <div class="rounded-2xl border border-slate-200/80 bg-white p-8 text-center dark:border-slate-800/80 dark:bg-slate-900">
                        <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ $config['copy']['empty']['title'] }}</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $config['copy']['empty']['body'] }}</p>
                        <p class="mt-3">
                            <a href="{{ $config['urls']['services'] }}" class="text-sm font-semibold text-primary hover:underline">
                                {{ $config['copy']['empty']['cta'] }}
                            </a>
                        </p>
                    </div>
                @endforelse
            </div>
        </noscript>

        {{-- Laravel keeps pagination server-rendered: the links are URLs and
             markup, which is exactly what Blade still owns. --}}
        @if ($purchases->hasPages())
            <div class="mt-6">{{ $purchases->links() }}</div>
        @endif
    </div>

    @vite('resources/js/teacher-orders/main.js')
</x-app-layout>
