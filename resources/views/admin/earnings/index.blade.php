@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);

    $tiles = [
        ['label' => __('Collected'), 'value' => $totals['collected'], 'note' => trans_choice('{0}No settled payments|{1}:count settled payment|[2,*]:count settled payments', $totals['payments'], ['count' => $totals['payments']]), 'tone' => 'text-emerald-600 dark:text-emerald-400'],
        ['label' => __('Platform commission'), 'value' => $totals['commission'], 'note' => __('Lesson + service commission'), 'tone' => 'text-primary'],
        ['label' => __('Booking fees'), 'value' => $totals['booking_fees'], 'note' => __('Checkout fee, net of offers'), 'tone' => 'text-primary'],
        ['label' => __('Refunded'), 'value' => $totals['refunded'], 'note' => __('Processed in range'), 'tone' => 'text-rose-600 dark:text-rose-400'],
    ];

    $columns = [
        __('Payments'),
        __('Collected'),
        __('Commission'),
        __('Booking fees'),
        __('Refunded'),
        __('Teacher payout'),
        __('Net platform'),
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Platform earnings') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ __(':from → :to', ['from' => $from->format('d M Y'), 'to' => $to->format('d M Y')]) }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.reports.export', ['type' => 'earnings', 'from' => $from->toDateString(), 'to' => $to->toDateString()]) }}"
                   class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition-colors hover:border-primary hover:text-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    {{ __('Export CSV') }}
                </a>
                <a href="{{ route('admin.payments.index') }}"
                   class="inline-flex items-center rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary/90">
                    {{ __('All payments') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6">
        <form method="GET" action="{{ route('admin.earnings.index') }}" class="{{ $cardBase }}" :class="{{ $cardTheme }}">
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
                    <a href="{{ route('admin.earnings.index') }}" class="text-sm font-semibold text-slate-500 hover:text-primary">{{ __('Last 30 days') }}</a>
                </div>
            </div>
        </form>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($tiles as $tile)
                <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $tile['label'] }}</p>
                    <p class="mt-2 text-2xl font-extrabold tracking-tight {{ $tile['tone'] }}">{{ $money($tile['value']) }}</p>
                    <p class="mt-1 text-xs text-slate-400 dark:text-slate-500">{{ $tile['note'] }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Net platform earnings -->
            <div class="lg:col-span-2 relative overflow-hidden rounded-2xl bg-gradient-to-r from-slate-800 via-slate-900 to-primary p-6 text-white shadow-lg shadow-primary/10 dark:shadow-primary/5">
                <p class="text-xs font-semibold uppercase tracking-wider text-white/70">{{ __('Net platform earnings') }}</p>
                <p class="mt-2 text-3xl font-extrabold tracking-tight">{{ $money($totals['net']) }}</p>
                <p class="mt-2 max-w-md text-sm text-white/80">
                    {{ __('Collected minus refunds and teacher payouts — what the platform keeps from the collections in this range.') }}
                </p>
                <div class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-xs text-white/80">
                    <span>{{ __('Collected') }}: <span class="font-semibold text-white">{{ $money($totals['collected']) }}</span></span>
                    <span>{{ __('Refunded') }}: <span class="font-semibold text-white">{{ $money($totals['refunded']) }}</span></span>
                    <span>{{ __('Teacher payouts') }}: <span class="font-semibold text-white">{{ $money($totals['teacher']) }}</span></span>
                </div>
            </div>

            <!-- Payout position -->
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Teacher payouts') }}</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500 dark:text-slate-400">{{ __('Due (unbatched)') }}</dt>
                        <dd class="font-semibold text-amber-600 dark:text-amber-400">{{ $money($payoutsDue) }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500 dark:text-slate-400">{{ __('Queued batches') }}</dt>
                        <dd class="font-semibold text-slate-800 dark:text-slate-100">{{ $money($payoutsQueued) }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500 dark:text-slate-400">{{ __('Released (all time)') }}</dt>
                        <dd class="font-semibold text-slate-800 dark:text-slate-100">{{ $money($payoutsReleased) }}</dd>
                    </div>
                    <div class="flex items-center justify-between border-t border-slate-100 pt-3 dark:border-slate-800">
                        <dt class="text-slate-500 dark:text-slate-400">{{ __('Payouts in range') }}</dt>
                        <dd class="font-semibold text-slate-800 dark:text-slate-100">{{ $money($totals['teacher']) }}</dd>
                    </div>
                </dl>
                <a href="{{ route('admin.payouts.index') }}" class="mt-4 inline-flex text-xs font-semibold text-primary hover:underline">{{ __('Open payout batches →') }}</a>
            </div>
        </div>

        <!-- Collections by source -->
        <div class="{{ $cardBase }} p-0" :class="{{ $cardTheme }}">
            <div class="flex flex-wrap items-center justify-between gap-3 px-6 pt-6">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Collections by source') }}</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Commission, booking fees and refunds split by what was sold.') }}</p>
            </div>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-400">
                            <th class="px-6 py-3">{{ __('Source') }}</th>
                            @foreach ($columns as $label)
                                <th class="px-6 py-3 text-right">{{ $label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                        @foreach ($sources as $source)
                            <tr>
                                <td class="px-6 py-4">
                                    <p class="font-semibold text-slate-800 dark:text-slate-100">{{ $source['label'] }}</p>
                                    <p class="text-xs text-slate-400">{{ trans_choice('{0}No payments|{1}:count payment|[2,*]:count payments', $source['payments'], ['count' => $source['payments']]) }}</p>
                                </td>
                                <td class="px-6 py-4 text-right text-slate-400">—</td>
                                <td class="px-6 py-4 text-right font-semibold text-slate-800 dark:text-slate-100">{{ $money($source['collected']) }}</td>
                                <td class="px-6 py-4 text-right text-primary">{{ $money($source['commission']) }}</td>
                                <td class="px-6 py-4 text-right text-primary">{{ $money($source['booking_fees']) }}</td>
                                <td class="px-6 py-4 text-right text-rose-600 dark:text-rose-400">{{ $money($source['refunded']) }}</td>
                                <td class="px-6 py-4 text-right text-slate-600 dark:text-slate-300">{{ $money($source['teacher']) }}</td>
                                <td class="px-6 py-4 text-right font-semibold text-emerald-600 dark:text-emerald-400">{{ $money($source['net']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-slate-50 text-sm dark:bg-slate-800/40">
                            <td class="px-6 py-4 font-bold text-slate-800 dark:text-slate-100">{{ __('All collections') }}</td>
                            <td class="px-6 py-4 text-right font-semibold text-slate-600 dark:text-slate-300">{{ $totals['payments'] }}</td>
                            <td class="px-6 py-4 text-right font-bold text-slate-800 dark:text-slate-100">{{ $money($totals['collected']) }}</td>
                            <td class="px-6 py-4 text-right font-semibold text-primary">{{ $money($totals['commission']) }}</td>
                            <td class="px-6 py-4 text-right font-semibold text-primary">{{ $money($totals['booking_fees']) }}</td>
                            <td class="px-6 py-4 text-right font-semibold text-rose-600 dark:text-rose-400">{{ $money($totals['refunded']) }}</td>
                            <td class="px-6 py-4 text-right font-semibold text-slate-600 dark:text-slate-300">{{ $money($totals['teacher']) }}</td>
                            <td class="px-6 py-4 text-right font-bold text-emerald-600 dark:text-emerald-400">{{ $money($totals['net']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Monthly ledger -->
        <div class="{{ $cardBase }} p-0" :class="{{ $cardTheme }}">
            <div class="flex flex-wrap items-center justify-between gap-3 px-6 pt-6">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Monthly ledger') }}</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Collections grouped by the month they were captured.') }}</p>
            </div>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-400">
                            <th class="px-6 py-3">{{ __('Month') }}</th>
                            @foreach ($columns as $label)
                                <th class="px-6 py-3 text-right">{{ $label }}</th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                        @forelse ($ledger as $month)
                            <tr>
                                <td class="px-6 py-4 font-semibold text-slate-800 dark:text-slate-100">{{ $month['month'] }}</td>
                                <td class="px-6 py-4 text-right text-slate-600 dark:text-slate-300">{{ $month['payments'] }}</td>
                                <td class="px-6 py-4 text-right font-semibold text-slate-800 dark:text-slate-100">{{ $money($month['collected']) }}</td>
                                <td class="px-6 py-4 text-right text-primary">{{ $money($month['commission']) }}</td>
                                <td class="px-6 py-4 text-right text-primary">{{ $money($month['booking_fees']) }}</td>
                                <td class="px-6 py-4 text-right text-rose-600 dark:text-rose-400">{{ $money($month['refunded']) }}</td>
                                <td class="px-6 py-4 text-right text-slate-600 dark:text-slate-300">{{ $money($month['teacher']) }}</td>
                                <td class="px-6 py-4 text-right font-semibold text-emerald-600 dark:text-emerald-400">{{ $money($month['net']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                                    {{ __('No collections were captured in this range.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Collections -->
        <div class="{{ $cardBase }} p-0" :class="{{ $cardTheme }}">
            <div class="flex flex-wrap items-center justify-between gap-3 px-6 pt-6">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Collections') }}</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Every settled payment and the platform earning it produced.') }}</p>
            </div>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-400">
                            <th class="px-6 py-3">{{ __('Payment') }}</th>
                            <th class="px-6 py-3">{{ __('Source') }}</th>
                            @foreach ($columns as $label)
                                <th class="px-6 py-3 text-right">{{ $label }}</th>
                            @endforeach
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                        @forelse ($collections as $row)
                            @php $payment = $row['payment']; @endphp
                            <tr>
                                <td class="px-6 py-4">
                                    <p class="font-mono text-xs text-slate-700 dark:text-slate-200">{{ $payment->gateway_payment_id ?? $payment->gateway_order_id ?? '#'.$payment->id }}</p>
                                    <p class="text-xs text-slate-400">
                                        {{ $payment->captured_at?->copy()->setTimezone($timezone)->format('d M Y, H:i') ?? $payment->created_at->format('d M Y, H:i') }}
                                    </p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $payment->student?->name }}</p>
                                </td>
                                <td class="px-6 py-4">
                                    @if ($payment->purchase)
                                        <p class="font-semibold text-slate-800 dark:text-slate-100">{{ $payment->purchase->scopeLabel() }}</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $payment->purchase->teacherProfile?->user?->name }}</p>
                                    @elseif ($payment->booking)
                                        <p class="font-semibold text-slate-800 dark:text-slate-100">#{{ str_pad((string) $payment->booking_id, 6, '0', STR_PAD_LEFT) }}</p>
                                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $payment->booking->teacherProfile?->user?->name }}</p>
                                    @else
                                        <span class="text-slate-400">{{ __('—') }}</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right text-slate-400">—</td>
                                <td class="px-6 py-4 text-right font-semibold text-slate-800 dark:text-slate-100">{{ $money($row['collected']) }}</td>
                                <td class="px-6 py-4 text-right text-primary">{{ $money($row['commission']) }}</td>
                                <td class="px-6 py-4 text-right text-primary">{{ $money($row['booking_fee']) }}</td>
                                <td class="px-6 py-4 text-right text-rose-600 dark:text-rose-400">{{ $money($row['refunded']) }}</td>
                                <td class="px-6 py-4 text-right text-slate-600 dark:text-slate-300">{{ $money($row['teacher']) }}</td>
                                <td class="px-6 py-4 text-right font-semibold text-emerald-600 dark:text-emerald-400">{{ $money($row['net']) }}</td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('admin.payments.show', $payment) }}" class="text-sm font-semibold text-primary hover:underline">{{ __('Details') }}</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                                    {{ __('No collections were captured in this range.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($collections->hasPages())
                <div class="border-t border-slate-200 px-6 py-4 dark:border-slate-800">{{ $collections->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
