@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);
    $tiles = [
        ['label' => __('Available for payout'), 'value' => $totals['available'], 'tone' => 'text-emerald-600 dark:text-emerald-400'],
        ['label' => __('Pending lessons'), 'value' => $totals['pending'], 'tone' => 'text-amber-600 dark:text-amber-400'],
        ['label' => __('Paid out'), 'value' => $totals['paid'], 'tone' => 'text-slate-700 dark:text-slate-200'],
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Earnings') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Platform commission is :percent%.', ['percent' => platform_settings()->int('commission_percent')]) }}
                </p>
            </div>
            <a href="{{ route('teacher.schedule.index') }}"
               class="inline-flex items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                {{ __('Back to schedule') }}
            </a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach ($tiles as $tile)
                <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $tile['label'] }}</p>
                    <p class="mt-2 text-2xl font-extrabold tracking-tight {{ $tile['tone'] }}">{{ $money($tile['value']) }}</p>
                </div>
            @endforeach
        </div>

        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('Ledger') }}</h3>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                {{ __('A lesson becomes available once it is delivered; reversed amounts are shown next to the original earning.') }}
            </p>

            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-400">
                            <th class="py-3 pr-4">{{ __('Lesson') }}</th>
                            <th class="px-4 py-3">{{ __('When') }}</th>
                            <th class="px-4 py-3">{{ __('Status') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Earned') }}</th>
                            <th class="py-3 pl-4 text-right">{{ __('Reversed') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                        @forelse ($ledger as $earning)
                            <tr>
                                <td class="py-4 pr-4">
                                    <p class="font-semibold text-slate-800 dark:text-slate-100">
                                        {{ $earning->booking->subject?->name }}@if ($earning->booking->topic) · {{ $earning->booking->topic->name }}@endif
                                    </p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ $earning->booking->learner_name ?: $earning->booking->student->name }}
                                    </p>
                                </td>
                                <td class="px-4 py-4 text-slate-600 dark:text-slate-300">
                                    {{ $earning->booking->starts_at->copy()->setTimezone($timezone)->format('d M Y') }}
                                </td>
                                <td class="px-4 py-4">
                                    <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $earning->status->badgeClasses() }}">{{ $earning->status->label() }}</span>
                                    @if ($earning->payout)
                                        <span class="mt-1 block font-mono text-[11px] text-slate-400">{{ $earning->payout->reference }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-4 text-right font-semibold text-slate-800 dark:text-slate-100">{{ $money($earning->amount_minor) }}</td>
                                <td class="py-4 pl-4 text-right font-semibold {{ $earning->reversed_minor > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-300 dark:text-slate-600' }}">
                                    {{ $earning->reversed_minor > 0 ? '−'.$money($earning->reversed_minor) : '—' }}
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                                    {{ __('No earnings yet — your first paid lesson will appear here.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($ledger->hasPages())
                <div class="mt-4">{{ $ledger->links() }}</div>
            @endif

            @if ($totals['reversed'] > 0)
                <p class="mt-4 border-t border-slate-200 pt-4 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
                    {{ __('Reversed by refunds: :amount.', ['amount' => $money($totals['reversed'])]) }}
                </p>
            @endif
        </div>

        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('Payouts') }}</h3>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                {{ __('Payouts are transferred manually by the Studylikepro team in batches.') }}
            </p>

            @forelse ($payouts as $payout)
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 pt-4 text-sm dark:border-slate-800">
                    <div>
                        <p class="font-semibold text-slate-800 dark:text-slate-100">{{ $payout->reference }}</p>
                        <p class="text-xs text-slate-400">
                            {{ trans_choice(':count lesson|:count lessons', $payout->lessons_count, ['count' => $payout->lessons_count]) }}
                            · {{ $payout->paid_at?->format('d M Y') ?? __('queued') }}
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="font-bold text-slate-800 dark:text-slate-100">{{ $money($payout->amount_minor) }}</p>
                        <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $payout->status->badgeClasses() }}">{{ $payout->status->label() }}</span>
                    </div>
                </div>
            @empty
                <p class="mt-4 rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                    {{ __('No payouts yet.') }}
                </p>
            @endforelse
        </div>
    </div>
</x-app-layout>
