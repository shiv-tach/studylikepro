@php
    use App\Enums\MarkingOrderStatus;
    use App\Enums\PurchaseStatus;
    use App\Enums\ServiceType;
    use Illuminate\Support\Facades\Gate;

    // My purchases is rendered by resources/js/student-purchases. Blade owns the
    // copy, the tab and filter URLs and the pre-formatted purchase cards, then
    // hands them over as a JSON island, so no URL, figure or translated string
    // is ever hardcoded in the Vue app.
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);

    // Whatever is in the query string that is not a tab, a service type or a page
    // number survives every link below, so switching one filter keeps the rest.
    $carried = collect(request()->query())->except(['page', 'tab', 'type'])->all();
    $indexUrl = fn (array $params = []) => route('student.purchases.index', array_filter(
        [...$carried, 'tab' => $tab, 'type' => $selectedType, ...$params],
        fn ($value) => $value !== null && $value !== '',
    ));

    $tabs = collect(['active', 'past'])->map(fn (string $key) => [
        'key' => $key,
        'label' => ($key === 'active' ? __('Active') : __('Past')).' ('.$counts[$key].')',
        'url' => $indexUrl(['tab' => $key]),
    ])->values()->all();

    // Every service type is offered, not only the ones already bought: an empty
    // result is exactly what tells the student they have none of that kind yet.
    $typeIcon = fn (ServiceType $type) => match ($type) {
        ServiceType::Lesson => 'graduation',
        ServiceType::PaperMarking => 'clipboard',
        ServiceType::PaperDiscussion => 'users',
        ServiceType::Qna => 'sparkles',
    };

    $typeOptions = collect([null, ...ServiceType::cases()])->map(fn (?ServiceType $type) => [
        'value' => $type?->value ?? '',
        'label' => $type?->pluralLabel() ?? __('All services'),
        'icon' => $type === null ? null : $typeIcon($type),
        'url' => $indexUrl(['type' => $type?->value]),
    ])->values()->all();

    // The three tiles never overlap: what still needs paying for, what the
    // student can already use, and what is over. They count the whole history,
    // not just the page, so they come from the controller's grouped counts.
    $stats = [
        ['key' => 'pending', 'icon' => 'clock', 'tone' => 'amber', 'value' => $counts['pending'], 'label' => __('Awaiting payment')],
        ['key' => 'ready', 'icon' => 'check-circle', 'tone' => 'primary', 'value' => $counts['active'] - $counts['pending'], 'label' => __('Ready to use')],
        ['key' => 'past', 'icon' => 'folder', 'tone' => 'slate', 'value' => $counts['past'], 'label' => __('Finished')],
    ];

    $cards = collect($purchases->items())->map(function ($purchase) use ($money, $typeIcon) {
        $allocation = (int) $purchase->allocation_minutes;

        // A hold whose window has closed is already lost even though the
        // scheduler has not written the status yet, so the card reads it as
        // expired and never offers a payment the gateway would refuse.
        $status = $purchase->isHoldLapsed() ? PurchaseStatus::Expired : $purchase->status;

        // Only a consumable lesson plan carries a balance, so the remaining
        // minutes are read for exactly the cards that display them.
        $remaining = $purchase->isLessonPlan() && $allocation > 0 && $status->isConsumable()
            ? $purchase->remainingMinutes()
            : null;

        // A live hold is the only card racing a clock, so the countdown — and
        // the expiry it can reach while the page stays open — are its business.
        $payBy = $status === PurchaseStatus::PendingPayment && $purchase->expires_at?->isFuture()
            ? ['deadline' => $purchase->expires_at->toIso8601String()]
            : null;

        // A capped plan has to know whether it still has sessions left before
        // offering another; an uncapped one never spends a query finding out.
        $sessionsLeft = $purchase->isLessonPlan()
            && $purchase->status === PurchaseStatus::Active
            && $purchase->max_sessions !== null
                ? $purchase->sessionsRemaining()
                : null;

        $canBookSession = Gate::allows('scheduleSession', $purchase)
            && ($remaining ?? 0) > 0
            && ($sessionsLeft === null || $sessionsLeft > 0);

        // The teacher only starts work once the purchase is paid for, so an
        // unpaid hold says nothing about what has been delivered yet.
        $delivery = $purchase->paid_at === null ? null : match (true) {
            $purchase->type() === ServiceType::PaperMarking && $purchase->markingOrder !== null => match (true) {
                $purchase->markingOrder->status === MarkingOrderStatus::Returned => ['label' => __('Marked paper ready'), 'tone' => 'emerald'],
                $purchase->markingOrder->isOverdue() => ['label' => __('Past the turnaround deadline'), 'tone' => 'rose'],
                default => ['label' => __('Being marked'), 'tone' => 'amber'],
            },
            default => null,
        };

        // Only a window that has not closed yet earns a chip; a deadline that
        // already passed would read as the opposite of its meaning.
        $expiresLabel = $payBy === null && $status->isConsumable() && $purchase->expires_at?->isFuture()
            ? __('Usable until :date', ['date' => $purchase->expires_at->format('d M Y')])
            : null;

        $title = $purchase->scopeLabel();
        $scope = collect([$purchase->subject?->name, $purchase->lesson?->name])->filter()->implode(' · ');

        // One action per card, decided here so the Vue component never has to
        // know which statuses may be paid, booked or opened.
        $action = match (true) {
            $status === PurchaseStatus::PendingPayment => [
                'kind' => 'form',
                'url' => route('student.purchases.checkout', $purchase),
                'label' => __('Pay now'),
                'variant' => 'primary',
            ],
            $canBookSession => [
                'kind' => 'link',
                'url' => route('student.purchase-sessions.create', $purchase),
                'label' => __('Book a session'),
                'variant' => 'primary',
            ],
            $purchase->markingOrder !== null => [
                'kind' => 'link',
                'url' => route('student.marking-orders.show', $purchase->markingOrder),
                'label' => $purchase->markingOrder->status === MarkingOrderStatus::Returned ? __('Open marked paper') : __('Track marking'),
                'variant' => 'outline',
            ],
            default => [
                'kind' => 'link',
                'url' => route('student.purchases.show', $purchase),
                'label' => __('View details'),
                'variant' => 'outline',
            ],
        };

        return [
            'id' => $purchase->id,
            'url' => route('student.purchases.show', $purchase),
            'typeIcon' => $typeIcon($purchase->type()),
            'typeLabel' => $purchase->type()->label(),
            'status' => $status->value,
            'statusLabel' => $status->label(),
            'title' => $title,
            'teacherLabel' => __('with :name', ['name' => $purchase->teacherProfile->user->name]),
            // The scope line is dropped when it would only repeat the title.
            'scope' => $scope === $title ? null : $scope,
            'price' => $money($purchase->price_minor),
            'purchasedLabel' => __('Bought :date', ['date' => $purchase->created_at->format('d M Y')]),
            'balance' => $remaining === null ? null : [
                'label' => $remaining > 0 ? __(':minutes min left', ['minutes' => $remaining]) : __('Fully booked'),
                'tone' => $remaining > 0 ? 'primary' : 'emerald',
            ],
            'progress' => $remaining === null ? null : [
                'percent' => (int) round((($allocation - $remaining) / $allocation) * 100),
                'exhausted' => $remaining === 0,
                'label' => __(':used of :total min used', ['used' => $allocation - $remaining, 'total' => $allocation]),
            ],
            'delivery' => $delivery,
            'expiresLabel' => $expiresLabel,
            // The deadline is an instant, not a duration: the card counts it
            // down itself so the seconds tick without a request.
            'payBy' => $payBy,
            // What the card switches to if the countdown reaches zero while the
            // page stays open, so it never keeps offering a payment that lapsed.
            'expiredStatus' => $payBy === null ? null : [
                'value' => PurchaseStatus::Expired->value,
                'label' => PurchaseStatus::Expired->label(),
            ],
            'action' => $action,
        ];
    })->values()->all();

    $purchasesPayload = [
        'appName' => config('app.name', 'Studylikepro'),
        'csrfToken' => csrf_token(),
        'theme' => ['preset' => auth()->user()->theme['preset']],
        'flash' => ['cancelled' => session('status') === 'purchase-cancelled'],
        'tab' => $tab,
        'tabs' => $tabs,
        'typeOptions' => $typeOptions,
        'selectedType' => $selectedType ?? '',
        'stats' => $stats,
        'purchases' => $cards,
        // A service-type filter is the only thing that can empty a list that has
        // purchases in it, so the empty state offers to clear exactly that.
        'filtered' => $selectedType !== null,
        'copy' => [
            'cancelled' => __('The purchase was cancelled.'),
            'overviewTitle' => __('Your wallet'),
            'overviewIntro' => __('Every service you have bought, and how much of each is left.'),
            'filtersLabel' => __('Filter your purchases'),
            'tabsLabel' => __('Purchase views'),
            'serviceLabel' => __('Service'),
            // The countdown fills :time in itself, so the ticking seconds are
            // never rendered — or rounded — by Blade.
            'payWithin' => __('Pay within :time', ['time' => ':time']),
            'holdExpired' => __('Hold expired'),
            'refresh' => __('Refresh'),
            'emptyTitle' => __('No purchases yet'),
            'emptyBody' => __('Pick a teacher and choose one of their services — a lesson plan, paper marking or a discussion — and it lands here the moment you buy it.'),
            'emptyCta' => __('Find a teacher'),
            'filteredTitle' => __('Nothing matches this filter'),
            'filteredBody' => __('Try another service type, or switch between Active and Past.'),
            'filteredCta' => __('Clear the filter'),
        ],
        'urls' => [
            'index' => route('student.purchases.index'),
            'reset' => $indexUrl(['type' => null]),
            'teachers' => route('student.teachers.index'),
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
                <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('My purchases') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Lesson plans, paper marking, discussions and Q&A.') }}</p>
            </div>

            <a href="{{ route('student.teachers.index') }}"
               class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary/90">
                {{ __('Find a teacher') }}
            </a>
        </div>
    </x-slot>

    <div class="w-full">
        <script type="application/json" id="student-purchases-config">@json($purchasesPayload, $jsonFlags)</script>

        <div id="student-purchases-app" class="space-y-6"></div>

        {{-- Fallback for a browser without JavaScript: the page is a thin shell,
             so list the same purchases the Vue app would have rendered. --}}
        <noscript>
            <div class="space-y-6">
                <div class="flex flex-wrap gap-2">
                    @foreach ($tabs as $tabLink)
                        <a href="{{ $tabLink['url'] }}"
                           class="rounded-xl px-4 py-2 text-sm font-semibold {{ $tab === $tabLink['key']
                                ? 'bg-primary text-white shadow-sm'
                                : 'border border-slate-200 text-slate-600 dark:border-slate-800 dark:text-slate-300' }}">
                            {{ $tabLink['label'] }}
                        </a>
                    @endforeach
                </div>

                @forelse ($purchases as $purchase)
                    <div class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                        <p class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                            {{ $purchase->type()->label() }} · {{ $purchase->status->label() }} ·
                            {{ platform_settings()->formatMinor($purchase->price_minor) }}
                        </p>
                        <h3 class="mt-2 text-sm font-bold text-slate-800 dark:text-slate-100">
                            <a class="hover:underline" href="{{ route('student.purchases.show', $purchase) }}">
                                {{ $purchase->scopeLabel() }}
                            </a>
                        </h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            {{ __('with :teacher', ['teacher' => $purchase->teacherProfile->user->name]) }}
                        </p>
                    </div>
                @empty
                    <div class="rounded-2xl border border-slate-200/80 bg-white p-6 text-center dark:border-slate-800/80 dark:bg-slate-900">
                        <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ __('No purchases yet') }}</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            {{ __('Pick a teacher and choose one of their services — a lesson plan, a summary, paper marking or a discussion — and it lands here the moment you buy it.') }}
                        </p>
                    </div>
                @endforelse

                <p class="text-sm text-slate-500 dark:text-slate-400">
                    <a class="font-semibold text-primary hover:underline" href="{{ route('student.teachers.index') }}">
                        {{ __('Find a teacher') }}
                    </a>
                </p>
            </div>
        </noscript>

        {{-- Laravel keeps pagination server-rendered: the links are URLs and
             markup, which is exactly what Blade still owns. --}}
        @if ($purchases->hasPages())
            <div class="mt-8">{{ $purchases->links() }}</div>
        @endif
    </div>

    @vite('resources/js/student-purchases/main.js')
</x-app-layout>
