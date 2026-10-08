@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);
    $timezone = config('studylikepro.default_display_timezone');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('admin.purchases.index') }}" class="text-xs font-semibold text-slate-400 transition-colors hover:text-primary">&larr; {{ __('Service purchases') }}</a>
            <h2 class="mt-1 font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">
                {{ $purchase->type()->icon() }} {{ $purchase->scopeLabel() }}
            </h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ $purchase->student->name }} · {{ $purchase->teacherProfile->user->name }}
            </p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        @if (session('status') === 'purchase-refunded')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                {{ __('Refund issued and the purchase was cancelled.') }}
            </div>
        @elseif (session('status') === 'purchase-cancelled')
            <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4 text-sm text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">
                {{ __('Purchase cancelled.') }}
            </div>
        @endif

        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <dl class="grid gap-4 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Status') }}</dt>
                    <dd class="mt-1"><span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $purchase->status->badgeClasses() }}">{{ $purchase->status->label() }}</span></dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Price') }}</dt>
                    <dd class="mt-1 font-bold text-slate-800 dark:text-slate-100">{{ $money($purchase->price_minor) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Teacher payout') }}</dt>
                    <dd class="mt-1 font-semibold text-slate-700 dark:text-slate-200">{{ $money($purchase->teacher_payout_minor) }} <span class="text-xs font-normal text-slate-400">({{ $purchase->commission_percent }}%)</span></dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Booking fee') }}</dt>
                    <dd class="mt-1 font-semibold text-slate-700 dark:text-slate-200">{{ $money($purchase->netBookingFeeMinor()) }}</dd>
                </div>
                @if ($purchase->isLessonPlan() && $purchase->allocation_minutes)
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Allocation') }}</dt>
                        <dd class="mt-1 font-semibold text-slate-700 dark:text-slate-200">{{ $purchase->allocation_minutes }} {{ __('min') }} · {{ $purchase->remainingMinutes() }} {{ __('left') }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Expires') }}</dt>
                        <dd class="mt-1 font-semibold text-slate-700 dark:text-slate-200">{{ $purchase->expires_at?->copy()->setTimezone($timezone)->format('d M Y') ?? '—' }}</dd>
                    </div>
                @endif
            </dl>
        </div>

        @if ($purchase->sessions->isNotEmpty())
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('Sessions') }}</h3>
                <ul class="mt-3 divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($purchase->sessions as $session)
                        <li class="flex items-center justify-between gap-3 py-2.5 text-sm">
                            <a href="{{ route('admin.bookings.show', $session) }}" class="font-semibold text-slate-700 hover:text-primary dark:text-slate-200">
                                {{ $session->starts_at->copy()->setTimezone($timezone)->format('D, d M Y · H:i') }} · {{ $session->durationMinutes() }} {{ __('min') }}
                            </a>
                            <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $session->status->badgeClasses() }}">{{ $session->status->label() }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('Payments') }}</h3>
            <ul class="mt-3 divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($purchase->payments as $payment)
                    <li class="flex items-center justify-between gap-3 py-2.5 text-sm">
                        <div>
                            <p class="font-semibold text-slate-700 dark:text-slate-200">{{ $money($payment->amount_minor) }} · {{ $payment->gateway }}</p>
                            <p class="text-xs text-slate-400">{{ $payment->captured_at?->copy()->setTimezone($timezone)->format('d M Y, H:i') ?? __('not captured') }}</p>
                        </div>
                        <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $payment->status->badgeClasses() }}">{{ $payment->status->label() }}</span>
                    </li>
                @empty
                    <li class="py-2.5 text-sm text-slate-400">{{ __('No payments.') }}</li>
                @endforelse
            </ul>
        </div>

        @if ($capturedPayment !== null && ! $purchase->status->isFinal())
            <form method="POST" action="{{ route('admin.purchases.refund', $purchase) }}" class="{{ $cardBase }} space-y-3" :class="{{ $cardTheme }}">
                @csrf
                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('Refund in full') }}</h3>
                <x-input-label for="reason" :value="__('Reason (shown on the refund record)')" />
                <textarea id="reason" name="reason" rows="3" required
                          class="block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">{{ old('reason') }}</textarea>
                <x-input-error :messages="$errors->get('reason')" />
                <button type="submit"
                        class="inline-flex items-center rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-rose-500">
                    {{ __('Refund and cancel') }}
                </button>
            </form>
        @endif
    </div>
</x-app-layout>
