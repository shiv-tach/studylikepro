@php
    // /teacher/earnings is rendered by resources/js/teacher-earnings. The
    // controller hands over a single payload (TeacherEarningsConfig) holding the
    // totals, the status counts, the six-month trend, every formatted amount and
    // the route map. The <noscript> block below repeats the same money without
    // JavaScript, and the paginator stays Blade-rendered because its links are
    // plain URLs.
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;

    // The no-JS twin stays preset-independent so it needs no theme JS.
    $cardClass = 'rounded-2xl border p-6 border-slate-200/80 bg-white dark:border-slate-800/80 dark:bg-slate-900';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Earnings') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Platform commission is :percent%.', ['percent' => $config['commissionPercent']]) }}
                </p>
            </div>
            <a href="{{ $config['urls']['schedule'] }}"
               class="inline-flex items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                {{ $config['copy']['hero']['lessonsCta'] }}
            </a>
        </div>
    </x-slot>

    <div class="w-full">
        <script type="application/json" id="teacher-earnings-config">@json($config, $jsonFlags)</script>

        <div id="teacher-earnings-app" class="space-y-6"></div>

        {{-- Fallback for a browser without JavaScript: the same tiles, the same
             status links, the same ledger and the same payouts. --}}
        <noscript>
            <div class="space-y-6">
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($config['stats']['tiles'] as $tile)
                        <div class="{{ $cardClass }}">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $tile['label'] }}</p>
                            <p class="mt-2 text-2xl font-extrabold tracking-tight text-slate-800 dark:text-slate-100">{{ $tile['valueLabel'] }}</p>
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $tile['caption'] }}</p>
                        </div>
                    @endforeach
                </div>

                <nav aria-label="{{ $config['copy']['ledger']['filtersLabel'] }}" class="flex flex-wrap gap-2">
                    @foreach ($config['statusTabs'] as $tab)
                        <a href="{{ $tab['url'] }}"
                           @if ($tab['active']) aria-current="page" @endif
                           class="inline-flex items-center gap-2 rounded-xl border px-3 py-2 text-sm font-semibold {{ $tab['active']
                               ? 'border-primary bg-primary/10 text-primary'
                               : 'border-slate-200 text-slate-600 hover:border-primary/40 hover:text-primary dark:border-slate-700 dark:text-slate-300' }}">
                            {{ $tab['label'] }}
                            <span class="rounded-full bg-slate-100 px-1.5 py-0.5 text-[11px] font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-400">{{ $tab['count'] }}</span>
                        </a>
                    @endforeach
                </nav>

                <div class="{{ $cardClass }}">
                    <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ $config['copy']['ledger']['title'] }}</h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $config['copy']['ledger']['subtitle'] }}</p>

                    @if ($config['ledger'] === [])
                        <p class="mt-4 rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                            {{ $config['pagination']['filtered'] ? $config['copy']['ledger']['filteredEmpty'] : $config['copy']['ledger']['empty'] }}
                        </p>
                        @if ($config['pagination']['filtered'])
                            <p class="mt-3 text-center text-sm">
                                <a href="{{ $config['urls']['index'] }}" class="font-semibold text-primary hover:text-primary/80">{{ $config['copy']['ledger']['clearFilter'] }}</a>
                            </p>
                        @endif
                    @else
                        <div class="mt-4 overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                                <thead>
                                    <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-400">
                                        <th class="py-3 pr-4">{{ $config['copy']['ledger']['headers']['item'] }}</th>
                                        <th class="px-4 py-3">{{ $config['copy']['ledger']['headers']['when'] }}</th>
                                        <th class="px-4 py-3">{{ $config['copy']['ledger']['headers']['status'] }}</th>
                                        <th class="px-4 py-3 text-right">{{ $config['copy']['ledger']['headers']['earned'] }}</th>
                                        <th class="py-3 pl-4 text-right">{{ $config['copy']['ledger']['headers']['reversed'] }}</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                                    @foreach ($config['ledger'] as $row)
                                        <tr>
                                            <td class="py-4 pr-4">
                                                <p class="font-semibold text-slate-800 dark:text-slate-100">
                                                    @if ($row['url'])
                                                        <a href="{{ $row['url'] }}" class="hover:text-primary">{{ $row['title'] }}</a>
                                                    @else
                                                        {{ $row['title'] }}
                                                    @endif
                                                </p>
                                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                                    {{ collect([$row['subtitle'], $row['typeLabel']])->filter()->implode(' · ') }}
                                                </p>
                                            </td>
                                            <td class="px-4 py-4 text-slate-600 dark:text-slate-300">{{ $row['dateLabel'] ?? '—' }}</td>
                                            <td class="px-4 py-4">
                                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $row['statusLabel'] }}</span>
                                                @if ($row['payoutReference'])
                                                    <span class="mt-1 block font-mono text-[11px] text-slate-400">{{ $row['payoutReference'] }}</span>
                                                @endif
                                            </td>
                                            <td class="px-4 py-4 text-right font-semibold text-slate-800 dark:text-slate-100">{{ $row['amountLabel'] }}</td>
                                            <td class="py-4 pl-4 text-right font-semibold {{ $row['reversedLabel'] ? 'text-rose-600 dark:text-rose-400' : 'text-slate-300 dark:text-slate-600' }}">
                                                {{ $row['reversedLabel'] ?? '—' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        @if ($config['stats']['reversed']['label'])
                            <p class="mt-4 border-t border-slate-200 pt-4 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
                                {{ $config['stats']['reversed']['label'] }}
                            </p>
                        @endif
                    @endif
                </div>

                <div class="{{ $cardClass }}">
                    <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ $config['copy']['payouts']['title'] }}</h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $config['copy']['payouts']['subtitle'] }}</p>

                    @forelse ($config['payouts'] as $payout)
                        <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-4 text-sm dark:border-slate-800">
                            <div>
                                <p class="font-mono font-semibold text-slate-800 dark:text-slate-100">{{ $payout['reference'] }}</p>
                                <p class="text-xs text-slate-400">{{ $payout['lessonsLabel'] }} · {{ $payout['paidLabel'] }}</p>
                            </div>
                            <div class="text-right">
                                <p class="font-bold text-slate-800 dark:text-slate-100">{{ $payout['amountLabel'] }}</p>
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $payout['statusLabel'] }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="mt-4 rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                            {{ $config['copy']['payouts']['empty'] }}
                        </p>
                    @endforelse
                </div>
            </div>
        </noscript>

        @if ($ledger->hasPages())
            <div class="mt-6">{{ $ledger->links() }}</div>
        @endif

        @vite('resources/js/teacher-earnings/main.js')
    </div>
</x-app-layout>
