@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Payouts') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Batch available earnings into a transfer, then mark it paid.') }}</p>
            </div>
            <a href="{{ route('admin.payments.index') }}"
               class="inline-flex items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                {{ __('Payments') }}
            </a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6">
        @if (session('status') === 'payout-created')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                {{ __('Payout queued. Transfer the money, then mark it paid.') }}
            </div>
        @elseif (session('status') === 'payout-paid')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                {{ __('Payout marked as paid.') }}
            </div>
        @elseif (session('status') === 'payout-empty')
            <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-300">
                {{ __('That teacher has nothing available to pay out yet.') }}
            </div>
        @endif

        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('Teachers with money on the books') }}</h3>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-400">
                            <th class="px-4 py-3">{{ __('Teacher') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Available') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Pending') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Lifetime') }}</th>
                            <th class="px-4 py-3 text-right">{{ __('Reversed') }}</th>
                            <th class="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                        @forelse ($teachers as $row)
                            <tr>
                                <td class="px-4 py-4">
                                    <p class="font-semibold text-slate-800 dark:text-slate-100">{{ $row['teacher']->user->name }}</p>
                                    <p class="text-xs text-slate-400">{{ $row['teacher']->user->email }}</p>
                                </td>
                                <td class="px-4 py-4 text-right font-semibold text-emerald-600 dark:text-emerald-400">{{ $money($row['totals']['available']) }}</td>
                                <td class="px-4 py-4 text-right text-amber-600 dark:text-amber-400">{{ $money($row['totals']['pending']) }}</td>
                                <td class="px-4 py-4 text-right text-slate-600 dark:text-slate-300">{{ $money($row['totals']['lifetime']) }}</td>
                                <td class="px-4 py-4 text-right text-rose-600 dark:text-rose-400">{{ $money($row['totals']['reversed']) }}</td>
                                <td class="px-4 py-4 text-right">
                                    @if ($row['unbatched'] > 0)
                                        <form method="POST" action="{{ route('admin.payouts.store') }}">
                                            @csrf
                                            <input type="hidden" name="teacher_profile_id" value="{{ $row['teacher']->id }}">
                                            <button type="submit" class="rounded-xl bg-primary px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-primary/90">
                                                {{ __('Queue :amount', ['amount' => $money($row['unbatched'])]) }}
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-xs text-slate-400">{{ __('Nothing queued') }}</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                                    {{ __('No teacher has earnings yet.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="{{ $cardBase }} p-0" :class="{{ $cardTheme }}">
            <h3 class="px-6 pt-6 text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('Payout batches') }}</h3>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-400">
                            <th class="px-6 py-3">{{ __('Reference') }}</th>
                            <th class="px-6 py-3">{{ __('Teacher') }}</th>
                            <th class="px-6 py-3">{{ __('Lessons') }}</th>
                            <th class="px-6 py-3">{{ __('Status') }}</th>
                            <th class="px-6 py-3 text-right">{{ __('Amount') }}</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                        @forelse ($payouts as $payout)
                            <tr>
                                <td class="px-6 py-4">
                                    <p class="font-mono text-xs text-slate-700 dark:text-slate-200">{{ $payout->reference }}</p>
                                    <p class="text-xs text-slate-400">{{ $payout->created_at->format('d M Y, H:i') }}</p>
                                </td>
                                <td class="px-6 py-4 text-slate-700 dark:text-slate-200">{{ $payout->teacherProfile->user->name }}</td>
                                <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ $payout->lessons_count }}</td>
                                <td class="px-6 py-4">
                                    <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $payout->status->badgeClasses() }}">{{ $payout->status->label() }}</span>
                                    @if ($payout->paid_at)
                                        <p class="mt-1 text-xs text-slate-400">{{ $payout->paid_at->format('d M Y') }}</p>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right font-semibold text-slate-800 dark:text-slate-100">{{ $money($payout->amount_minor) }}</td>
                                <td class="px-6 py-4 text-right">
                                    @if ($payout->status === \App\Enums\PayoutStatus::Pending)
                                        <form method="POST" action="{{ route('admin.payouts.mark-paid', $payout) }}" class="flex items-center justify-end gap-2">
                                            @csrf
                                            <input type="text" name="reference" placeholder="{{ __('UTR / notes') }}"
                                                   class="w-32 rounded-lg border-slate-300 bg-white py-1 text-xs dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                            <button type="submit" class="text-xs font-semibold text-primary hover:underline">{{ __('Mark paid') }}</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                                    {{ __('No payout batches yet.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($payouts->hasPages())
                <div class="border-t border-slate-200 px-6 py-4 dark:border-slate-800">{{ $payouts->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>
