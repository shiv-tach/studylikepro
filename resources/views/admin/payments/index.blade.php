@php
    use App\Enums\PaymentStatus;

    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $fieldClasses = 'rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);

    $tiles = [
        ['label' => __('Collected'), 'value' => $stats['collected'], 'tone' => 'text-emerald-600 dark:text-emerald-400'],
        ['label' => __('Refunded'), 'value' => $stats['refunded'], 'tone' => 'text-rose-600 dark:text-rose-400'],
        ['label' => __('Platform commission'), 'value' => $stats['fees'], 'tone' => 'text-primary'],
        ['label' => __('Payouts due'), 'value' => $stats['payouts_due'], 'tone' => 'text-amber-600 dark:text-amber-400'],
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Payments') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Every checkout attempt, capture and refund.') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="{{ route('admin.reports.export', ['type' => 'payments', 'from' => ($filters['from'] ?? null), 'to' => ($filters['to'] ?? null)]) }}"
                   class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition-colors hover:border-primary hover:text-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                    {{ __('Export CSV') }}
                </a>
                <a href="{{ route('admin.payouts.index') }}"
                   class="inline-flex items-center rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary/90">
                    {{ __('Payout batches') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6">
        @if (session('status') === 'refund-issued')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                {{ __('Refund issued and both parties notified.') }}
            </div>
        @elseif (session('status') === 'refund-skipped')
            <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-300">
                {{ __('Nothing to refund on that payment.') }}
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($tiles as $tile)
                <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $tile['label'] }}</p>
                    <p class="mt-2 text-2xl font-extrabold tracking-tight {{ $tile['tone'] }}">{{ $money($tile['value']) }}</p>
                </div>
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.payments.index') }}" class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <div class="grid gap-4 sm:grid-cols-6">
                <div>
                    <x-input-label for="filter-status" :value="__('Status')" />
                    <select id="filter-status" name="status" class="mt-1 block w-full {{ $fieldClasses }}">
                        <option value="">{{ __('Any status') }}</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="filter-gateway" :value="__('Gateway')" />
                    <select id="filter-gateway" name="gateway" class="mt-1 block w-full {{ $fieldClasses }}">
                        <option value="">{{ __('Any gateway') }}</option>
                        @foreach (['fake', 'razorpay'] as $gateway)
                            <option value="{{ $gateway }}" @selected(($filters['gateway'] ?? null) === $gateway)>{{ $gateway }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="filter-search" :value="__('Student or reference')" />
                    <x-text-input id="filter-search" name="search" type="text" class="mt-1 block w-full"
                                  :value="$filters['search'] ?? null" placeholder="{{ __('Name, email, order id') }}" />
                </div>
                <div>
                    <x-input-label for="filter-from" :value="__('From')" />
                    <x-text-input id="filter-from" name="from" type="date" class="mt-1 block w-full" :value="$filters['from'] ?? null" />
                </div>
                <div>
                    <x-input-label for="filter-to" :value="__('To')" />
                    <x-text-input id="filter-to" name="to" type="date" class="mt-1 block w-full" :value="$filters['to'] ?? null" />
                </div>
                <div class="flex items-end gap-3">
                    <x-primary-button>{{ __('Filter') }}</x-primary-button>
                    <a href="{{ route('admin.payments.index') }}" class="text-sm font-semibold text-slate-500 hover:text-primary">{{ __('Reset') }}</a>
                </div>
            </div>
        </form>

        <div class="{{ $cardBase }} p-0" :class="{{ $cardTheme }}">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-400">
                            <th class="px-6 py-3">{{ __('Payment') }}</th>
                            <th class="px-6 py-3">{{ __('Student & lesson') }}</th>
                            <th class="px-6 py-3">{{ __('Status') }}</th>
                            <th class="px-6 py-3 text-right">{{ __('Amount') }}</th>
                            <th class="px-6 py-3 text-right">{{ __('Refundable') }}</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                        @forelse ($payments as $payment)
                            <tr>
                                <td class="px-6 py-4">
                                    <p class="font-mono text-xs text-slate-700 dark:text-slate-200">{{ $payment->gateway_payment_id ?? $payment->gateway_order_id }}</p>
                                    <p class="text-xs text-slate-400">
                                        {{ $payment->gateway }} · {{ $payment->captured_at?->copy()->setTimezone($timezone)->format('d M Y, H:i') ?? $payment->created_at->format('d M Y, H:i') }}
                                    </p>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="font-semibold text-slate-800 dark:text-slate-100">{{ $payment->student->name }}</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        #{{ str_pad((string) $payment->booking_id, 6, '0', STR_PAD_LEFT) }}
                                        · {{ $payment->booking->teacherProfile->user->name }}
                                    </p>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $payment->status->badgeClasses() }}">{{ $payment->status->label() }}</span>
                                    @if ($payment->failure_reason)
                                        <p class="mt-1 text-xs text-rose-500">{{ $payment->failure_reason }}</p>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right font-semibold text-slate-800 dark:text-slate-100">{{ $money($payment->amount_minor) }}</td>
                                <td class="px-6 py-4 text-right text-slate-600 dark:text-slate-300">{{ $money($payment->refundableMinor()) }}</td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('admin.payments.show', $payment) }}" class="text-sm font-semibold text-primary hover:underline">
                                        {{ __('Details') }}
                                    </a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                                    {{ __('No payments match these filters yet.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($payments->hasPages())
                <div class="border-t border-slate-200 px-6 py-4 dark:border-slate-800">{{ $payments->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
