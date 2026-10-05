@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);

    $tiles = [
        ['label' => __('Collected'), 'value' => $money($kpis['gross']), 'tone' => 'text-emerald-600 dark:text-emerald-400'],
        ['label' => __('Refunded'), 'value' => $money($kpis['refunded']), 'tone' => 'text-rose-600 dark:text-rose-400'],
        ['label' => __('Net revenue'), 'value' => $money($kpis['net']), 'tone' => 'text-slate-800 dark:text-slate-100'],
        ['label' => __('Commission'), 'value' => $money($kpis['commission']), 'tone' => 'text-primary'],
    ];

    $counters = [
        ['label' => __('Payments captured'), 'value' => $kpis['payments']],
        ['label' => __('Lessons booked'), 'value' => $kpis['bookings']],
        ['label' => __('Lessons completed'), 'value' => $kpis['completed']],
        ['label' => __('Active teachers'), 'value' => $kpis['active_teachers']],
        ['label' => __('New students'), 'value' => $kpis['new_students']],
        ['label' => __('New teachers'), 'value' => $kpis['new_teachers']],
        ['label' => __('Verified teachers'), 'value' => $kpis['verified_teachers']],
        ['label' => __('Payouts sent'), 'value' => $money($kpis['payouts'])],
    ];

    $peak = collect($trend)->max('revenue') ?: 1;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Reports') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ __(':from → :to', ['from' => $from->format('d M Y'), 'to' => $to->format('d M Y')]) }}
            </p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6">
        <form method="GET" action="{{ route('admin.reports.index') }}" class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <div class="grid gap-4 sm:grid-cols-4">
                <div>
                    <x-input-label for="filter-from" :value="__('From')" />
                    <x-text-input id="filter-from" name="from" type="date" class="mt-1 block w-full" :value="$filters['from'] ?? $from->toDateString()" />
                </div>
                <div>
                    <x-input-label for="filter-to" :value="__('To')" />
                    <x-text-input id="filter-to" name="to" type="date" class="mt-1 block w-full" :value="$filters['to'] ?? $to->toDateString()" />
                </div>
                <div class="flex items-end gap-3 sm:col-span-2">
                    <x-primary-button>{{ __('Apply') }}</x-primary-button>
                    <a href="{{ route('admin.reports.index') }}" class="text-sm font-semibold text-slate-500 hover:text-primary">{{ __('Last 30 days') }}</a>
                </div>
            </div>
        </form>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($tiles as $tile)
                <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $tile['label'] }}</p>
                    <p class="mt-2 text-2xl font-extrabold tracking-tight {{ $tile['tone'] }}">{{ $tile['value'] }}</p>
                </div>
            @endforeach
        </div>

        <!-- Trend -->
        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Daily collections') }}</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Bar height is the day\'s captured payments.') }}</p>
            </div>

            <div class="mt-6 flex h-40 items-end gap-1 overflow-x-auto">
                @foreach ($trend as $day)
                    <div class="group flex min-w-[1.25rem] flex-1 flex-col items-center justify-end gap-1" title="{{ $day['label'] }}: {{ $money($day['revenue']) }} · {{ $day['bookings'] }} {{ __('lessons') }}">
                        <div class="w-full rounded-t bg-primary/70 transition-all group-hover:bg-primary"
                             style="height: {{ max(2, (int) round($day['revenue'] / $peak * 100)) }}%"></div>
                        <span class="hidden text-[10px] text-slate-400 sm:block">{{ $day['label'] }}</span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Counters -->
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Range totals') }}</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    @foreach ($counters as $counter)
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-500 dark:text-slate-400">{{ $counter['label'] }}</dt>
                            <dd class="font-semibold text-slate-800 dark:text-slate-100">{{ $counter['value'] }}</dd>
                        </div>
                    @endforeach
                    <div class="flex items-center justify-between border-t border-slate-100 pt-3 dark:border-slate-800">
                        <dt class="text-slate-500 dark:text-slate-400">{{ __('Payouts due now') }}</dt>
                        <dd class="font-semibold text-amber-600 dark:text-amber-400">{{ $money($kpis['payouts_due']) }}</dd>
                    </div>
                </dl>
            </div>

            <!-- Bookings by status -->
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Lessons by status') }}</h3>
                <ul class="mt-4 space-y-3 text-sm">
                    @foreach ($bookingsByStatus as $label => $count)
                        <li>
                            <div class="flex items-center justify-between">
                                <span class="text-slate-600 dark:text-slate-300">{{ $label }}</span>
                                <span class="font-semibold text-slate-800 dark:text-slate-100">{{ $count }}</span>
                            </div>
                            @php $share = $kpis['bookings'] > 0 ? (int) round($count / $kpis['bookings'] * 100) : 0; @endphp
                            <div class="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div class="h-full rounded-full bg-primary/70" style="width: {{ $share }}%"></div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>

            <!-- Exports -->
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Export CSV') }}</h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Downloads cover :from → :to.', ['from' => $from->format('d M Y'), 'to' => $to->format('d M Y')]) }}</p>

                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($exportTypes as $type)
                        <a href="{{ route('admin.reports.export', ['type' => $type, 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}"
                           class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm transition-colors hover:border-primary hover:text-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                            {{ ucfirst($type) }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Teacher performance -->
        <div class="{{ $cardBase }} p-0" :class="{{ $cardTheme }}">
            <div class="border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Teacher performance') }}</h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Completed lessons in the selected range, biggest first.') }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-400">
                            <th class="px-6 py-3">{{ __('Teacher') }}</th>
                            <th class="px-6 py-3 text-right">{{ __('Lessons') }}</th>
                            <th class="px-6 py-3 text-right">{{ __('Gross') }}</th>
                            <th class="px-6 py-3 text-right">{{ __('Commission') }}</th>
                            <th class="px-6 py-3 text-right">{{ __('Payout') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                        @forelse ($teachers as $row)
                            <tr>
                                <td class="px-6 py-4">
                                    <a href="{{ route('admin.users.show', $row['teacher']->user) }}" class="font-semibold text-slate-800 hover:text-primary dark:text-slate-100">
                                        {{ $row['teacher']->user->name }}
                                    </a>
                                    <p class="text-xs text-slate-400">★ {{ number_format((float) $row['teacher']->rating_avg, 1) }} · {{ $row['teacher']->lessons_completed_count }} {{ __('lifetime') }}</p>
                                </td>
                                <td class="px-6 py-4 text-right text-slate-700 dark:text-slate-200">{{ $row['lessons'] }}</td>
                                <td class="px-6 py-4 text-right font-semibold text-slate-800 dark:text-slate-100">{{ $money($row['gross']) }}</td>
                                <td class="px-6 py-4 text-right text-primary">{{ $money($row['commission']) }}</td>
                                <td class="px-6 py-4 text-right text-slate-700 dark:text-slate-200">{{ $money($row['net']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('No completed lessons in this range.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
