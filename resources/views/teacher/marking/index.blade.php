@php
    use App\Support\TeacherMarkingConfig;

    // /teacher/marking is rendered by resources/js/teacher-marking. Blade owns the
    // status counts, every formatted amount and date and every route, then hands
    // them over as a JSON island. The <noscript> block below repeats the rail and
    // the cards without JavaScript, and the paginator stays Blade-rendered because
    // its links are plain URLs.
    $config = TeacherMarkingConfig::build(
        $orders,
        $statusCounts,
        $overdueCount,
        $selectedStatus,
        $userTheme['preset'] ?? 'classic',
    );

    // JSON_UNESCAPED_SLASHES keeps the URLs readable; the HEX flags keep the
    // island safe inside a <script> tag.
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;

    // The no-JS twin stays preset-independent, so it maps the semantic tone names
    // onto literal Tailwind classes here.
    $cardClass = 'rounded-2xl border p-5 border-slate-200/80 bg-white dark:border-slate-800/80 dark:bg-slate-900';
    $toneClasses = [
        'amber' => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
        'primary' => 'bg-primary/10 text-primary',
        'sky' => 'bg-sky-100 text-sky-700 dark:bg-sky-900/30 dark:text-sky-400',
        'emerald' => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
        'rose' => 'bg-rose-100 text-rose-700 dark:bg-rose-900/30 dark:text-rose-400',
        'slate' => 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Paper marking') }}</h2>
    </x-slot>

    <div class="w-full space-y-6">
        <script type="application/json" id="teacher-marking-config">@json($config, $jsonFlags)</script>

        <div id="teacher-marking-app" class="space-y-6"></div>

        {{-- Fallback for a browser without JavaScript: the same status rail as
             plain links and the same orders as a list. --}}
        <noscript>
            <div class="space-y-6">
                @if (session('status') === 'marking-returned')
                    <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                        {{ __('Marked copy delivered — the student has been notified.') }}
                    </div>
                @endif

                <nav aria-label="{{ $config['copy']['rail']['label'] }}" class="flex flex-wrap gap-2">
                    @foreach ($config['statusTabs'] as $tab)
                        <a href="{{ $tab['url'] }}"
                           @if ($tab['active']) aria-current="page" @endif
                           class="rounded-xl border px-3 py-2 text-sm font-semibold {{ $tab['active']
                                ? 'border-primary bg-primary/10 text-primary'
                                : 'border-slate-200 text-slate-600 dark:border-slate-800 dark:text-slate-300' }}">
                            {{ $tab['label'] }} ({{ $tab['count'] }})
                        </a>
                    @endforeach
                </nav>

                @forelse ($config['orders'] as $order)
                    <div class="{{ $cardClass }}">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-primary/10 px-2.5 py-0.5 text-[11px] font-semibold text-primary">
                                {{ $order['subjectIcon'] }} {{ $order['subjectName'] }}
                            </span>
                            <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $toneClasses[$order['status']['tone']] ?? $toneClasses['slate'] }}">
                                {{ $order['status']['label'] }}
                            </span>
                            @if ($order['overdue'])
                                <span class="rounded-full bg-rose-100 px-2.5 py-0.5 text-[11px] font-semibold text-rose-700 dark:bg-rose-900/30 dark:text-rose-400">
                                    {{ $config['copy']['card']['overdue'] }}
                                </span>
                            @endif
                            @if ($order['submittedLabel'])
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                    {{ $order['submittedLabel'] }}
                                </span>
                            @endif
                        </div>

                        <h3 class="mt-3 text-sm font-bold text-slate-800 dark:text-slate-100">
                            <a href="{{ $order['url'] }}" class="hover:underline">{{ $order['studentName'] }}</a>
                        </h3>

                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            @if ($order['pagesLabel']){{ $order['pagesLabel'] }} · @endif
                            {{ $order['priceLabel'] }}
                            @if ($order['deadlineLabel']) · {{ $order['deadlineLabel'] }} @endif
                        </p>

                        <p class="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-400">
                            <span>{{ $order['reference'] }}</span>
                            <a href="{{ $order['url'] }}" class="font-semibold text-primary hover:underline">{{ $order['actionLabel'] }}</a>
                        </p>
                    </div>
                @empty
                    <div class="{{ $cardClass }} text-center">
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
        @if ($orders->hasPages())
            <div class="mt-6">{{ $orders->links() }}</div>
        @endif
    </div>

    @vite('resources/js/teacher-marking/main.js')
</x-app-layout>
