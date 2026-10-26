@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $chip = 'inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300';
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);
    $booking = $payment->booking;
    $purchase = $payment->purchase;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">
                {{ __('Payment #:id', ['id' => $payment->id]) }}
            </h2>
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $payment->status->badgeClasses() }}">{{ $payment->status->label() }}</span>
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6">
        <a href="{{ route('admin.payments.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 transition-colors hover:text-primary dark:text-slate-400">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            {{ __('Back to payments') }}
        </a>

        @if (session('status') === 'refund-issued')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                {{ __('Refund issued and both parties notified.') }}
            </div>
        @elseif (session('status') === 'refund-skipped')
            <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-300">
                {{ __('Nothing left to refund on this payment.') }}
            </div>
        @elseif (session('status') === 'payment-verified')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                {{ __('Payment verified — the booking or purchase is confirmed and the receipt is live.') }}
            </div>
        @elseif (session('status') === 'payment-rejected')
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
                {{ __('Slip rejected. The student has been asked to upload a better proof.') }}
            </div>
        @endif

        @if ($payment->payment_method || $payment->slip_path)
            @php
                $isSubmitted = $payment->status === \App\Enums\PaymentStatus::Submitted;
                $slipIsImage = $payment->slip_path !== null
                    && in_array(strtolower(pathinfo($payment->slip_path, PATHINFO_EXTENSION)), ['jpg', 'jpeg', 'png', 'webp'], true);
            @endphp

            <div class="rounded-2xl border border-sky-200/80 bg-sky-50/60 p-6 dark:border-sky-900/40 dark:bg-sky-950/20">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Manual payment proof') }}</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            {{ __('The student paid by :method and uploaded a slip. Confirm it only after the money shows in the account.', ['method' => $payment->payment_method?->label() ?? __('bank transfer')]) }}
                        </p>
                    </div>
                    <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $payment->status->badgeClasses() }}">{{ $payment->status->label() }}</span>
                </div>

                <dl class="mt-4 grid gap-4 border-t border-sky-200/70 pt-4 text-sm sm:grid-cols-2 lg:grid-cols-4 dark:border-sky-900/40">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-sky-700/70 dark:text-sky-400/80">{{ __('Paid into') }}</dt>
                        <dd class="mt-1 text-slate-800 dark:text-slate-100">
                            @if ($payment->bankAccount)
                                {{ $payment->bankAccount->bank_name }}
                                <span class="block text-xs text-slate-500 dark:text-slate-400">
                                    {{ $payment->bankAccount->account_name }} · <span class="font-mono">{{ $payment->bankAccount->account_number }}</span>
                                </span>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-sky-700/70 dark:text-sky-400/80">{{ __('Bank reference') }}</dt>
                        <dd class="mt-1 font-mono text-xs text-slate-700 dark:text-slate-200">{{ $payment->bank_reference ?: '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-sky-700/70 dark:text-sky-400/80">{{ __('Submitted') }}</dt>
                        <dd class="mt-1 text-slate-700 dark:text-slate-200">{{ display_datetime($payment->submitted_at?->copy()->setTimezone($timezone)) ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-sky-700/70 dark:text-sky-400/80">{{ __('Checked by') }}</dt>
                        <dd class="mt-1 text-slate-700 dark:text-slate-200">{{ $payment->verifier?->name ?? '—' }}</dd>
                    </div>
                </dl>

                @if ($payment->slip_path)
                    <div class="mt-4 border-t border-sky-200/70 pt-4 dark:border-sky-900/40">
                        <a href="{{ route('admin.payments.slip', $payment) }}" target="_blank" rel="noopener"
                           class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-3 py-1.5 text-sm font-semibold text-slate-700 transition-colors hover:border-primary hover:text-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                            {{ __('Open the slip') }}{{ $payment->slip_original_name ? ' · '.$payment->slip_original_name : '' }}
                        </a>

                        @if ($slipIsImage)
                            <img src="{{ route('admin.payments.slip', $payment) }}" alt="{{ __('Uploaded payment slip') }}"
                                 class="mt-3 max-h-80 w-auto rounded-xl border border-slate-200 dark:border-slate-800">
                        @endif
                    </div>
                @endif

                @error('payment')
                    <p class="mt-3 rounded-xl bg-rose-50 p-3 text-sm text-rose-700 dark:bg-rose-950/30 dark:text-rose-300">{{ $message }}</p>
                @enderror

                @if ($isSubmitted)
                    <div class="mt-5 grid gap-4 border-t border-sky-200/70 pt-5 sm:grid-cols-2 dark:border-sky-900/40">
                        <form method="POST" action="{{ route('admin.payments.verify', $payment) }}" class="space-y-2">
                            @csrf
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ __('Confirms the booking or activates the purchase, and tells the student and teacher.') }}</p>
                            <x-primary-button>{{ __('Verify and confirm payment') }}</x-primary-button>
                        </form>

                        <form method="POST" action="{{ route('admin.payments.reject', $payment) }}" class="space-y-2">
                            @csrf
                            <x-input-label for="reject-reason" :value="__('Reason for rejecting the slip')" />
                            <textarea id="reject-reason" name="reason" rows="2" required
                                      placeholder="{{ __('e.g. The reference does not match any deposit') }}"
                                      class="block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">{{ old('reason') }}</textarea>
                            @error('reason')
                                <p class="text-xs text-rose-600 dark:text-rose-400">{{ $message }}</p>
                            @enderror
                            <x-secondary-button>{{ __('Reject the slip') }}</x-secondary-button>
                        </form>
                    </div>
                @endif
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Transaction -->
            <div class="{{ $cardBase }} lg:col-span-2" :class="{{ $cardTheme }}">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Transaction') }}</h3>

                <dl class="mt-4 grid gap-5 sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Amount') }}</dt>
                        <dd class="mt-1 text-lg font-bold text-slate-800 dark:text-slate-100">{{ $money($payment->amount_minor) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Refundable') }}</dt>
                        <dd class="mt-1 text-lg font-bold text-slate-800 dark:text-slate-100">{{ $money($payment->refundableMinor()) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Gateway') }}</dt>
                        <dd class="mt-1 text-sm text-slate-700 dark:text-slate-200">{{ $payment->gateway }} @if ($payment->method) · {{ $payment->method }} @endif</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Reference') }}</dt>
                        <dd class="mt-1 font-mono text-xs text-slate-600 dark:text-slate-300">{{ $payment->gateway_payment_id ?? '—' }}</dd>
                        <dd class="font-mono text-xs text-slate-400">{{ $payment->gateway_order_id }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Created') }}</dt>
                        <dd class="mt-1 text-sm text-slate-700 dark:text-slate-200">{{ display_datetime($payment->created_at->copy()->setTimezone($timezone)) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Captured') }}</dt>
                        <dd class="mt-1 text-sm text-slate-700 dark:text-slate-200">{{ display_datetime($payment->captured_at?->copy()->setTimezone($timezone)) ?? '—' }}</dd>
                    </div>
                </dl>

                @if ($payment->failure_reason)
                    <p class="mt-5 rounded-xl bg-rose-50 p-3 text-sm text-rose-700 dark:bg-rose-950/30 dark:text-rose-300">{{ $payment->failure_reason }}</p>
                @endif
            </div>

            <!-- Student & lesson -->
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Student & lesson') }}</h3>

                <a href="{{ route('admin.users.show', $payment->student) }}" class="mt-3 block font-semibold text-slate-800 hover:text-primary dark:text-slate-100">
                    {{ $payment->student->name }}
                </a>
                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $payment->student->email }}</p>

                @if ($booking)
                    <div class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-800">
                        <a href="{{ route('admin.bookings.show', $booking) }}" class="text-sm font-semibold text-primary hover:underline">
                            {{ __('Lesson #:id', ['id' => str_pad((string) $booking->id, 6, '0', STR_PAD_LEFT)]) }}
                        </a>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ $booking->teacherProfile->user->name }}
                            @if ($booking->subject) · {{ $booking->subject->name }} @endif
                        </p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ display_datetime($booking->starts_at->copy()->setTimezone($timezone)) }}</p>
                        <p class="mt-2 text-xs text-slate-400">
                            {{ __('Lesson :price · booking fee :booking_fee', [
                                'price' => $money($booking->price_minor),
                                'booking_fee' => $money($booking->netBookingFeeMinor()),
                            ]) }}
                        </p>
                        <p class="text-xs text-slate-400">
                            {{ __('Commission :fee · payout :payout', [
                                'fee' => $money($booking->platform_fee_minor),
                                'payout' => $money($booking->teacher_payout_minor),
                            ]) }}
                        </p>
                    </div>
                @endif

                @if ($purchase)
                    <div class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-800">
                        <a href="{{ route('admin.purchases.show', $purchase) }}" class="text-sm font-semibold text-primary hover:underline">
                            {{ __(':type #:id', ['type' => $purchase->service_type->label(), 'id' => $purchase->id]) }}
                        </a>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ $purchase->teacherProfile->user->name }}
                            @if ($purchase->subject) · {{ $purchase->subject->name }} @endif
                        </p>
                        <p class="mt-2 text-xs text-slate-400">
                            {{ __('Price :price · commission :fee · payout :payout', [
                                'price' => $money($purchase->price_minor),
                                'fee' => $money($purchase->platform_fee_minor),
                                'payout' => $money($purchase->teacher_payout_minor),
                            ]) }}
                        </p>
                    </div>
                @endif
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Refund history -->
            <div class="{{ $cardBase }} p-0 lg:col-span-2" :class="{{ $cardTheme }}">
                <div class="border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Refund history') }}</h3>
                </div>
                <ul class="divide-y divide-slate-100 text-sm dark:divide-slate-800/60">
                    @forelse ($refunds as $refund)
                        <li class="flex items-start justify-between gap-4 px-6 py-4">
                            <div>
                                <p class="font-semibold text-slate-800 dark:text-slate-100">{{ $money($refund->amount_minor) }} <span class="text-xs font-normal text-slate-400">({{ $refund->percent }}%)</span></p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $refund->reason }}</p>
                                <p class="mt-1 text-xs text-slate-400">
                                    {{ ucfirst($refund->initiated_by) }}
                                    · {{ display_datetime($refund->created_at->copy()->setTimezone($timezone)) }}
                                    @if ($refund->gateway_refund_id) · <span class="font-mono">{{ $refund->gateway_refund_id }}</span> @endif
                                </p>
                            </div>
                            <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $refund->status->badgeClasses() }}">{{ $refund->status->label() }}</span>
                        </li>
                    @empty
                        <li class="px-6 py-6 text-sm text-slate-500 dark:text-slate-400">{{ __('No refunds on this payment.') }}</li>
                    @endforelse
                </ul>
            </div>

            <!-- Issue a refund -->
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Issue a refund') }}</h3>

                @if ($payment->canBeRefunded())
                    <form method="POST" action="{{ route('admin.payments.refund', $payment) }}" class="mt-4 space-y-3">
                        @csrf
                        <div>
                            <x-input-label for="refund-percent" :value="__('Percentage')" />
                            <select id="refund-percent" name="percent" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                @foreach ([100, 75, 50, 25] as $percent)
                                    <option value="{{ $percent }}" @selected(old('percent') == $percent)>{{ $percent }}% — {{ $money((int) round($payment->refundableMinor() * $percent / 100)) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <x-input-label for="refund-reason" :value="__('Reason')" />
                            <textarea id="refund-reason" name="reason" rows="3" placeholder="{{ __('Why is money going back?') }}"
                                      class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">{{ old('reason') }}</textarea>
                        </div>
                        <x-primary-button>{{ __('Refund') }}</x-primary-button>
                    </form>
                @else
                    <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                        {{ __('This payment has nothing left to refund.') }}
                    </p>
                @endif
            </div>
        </div>

        @if ($payment->gateway_payload)
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Gateway payload') }}</h3>
                <pre class="mt-3 max-h-72 overflow-auto rounded-xl bg-slate-900 p-4 font-mono text-xs text-slate-100 dark:bg-slate-950">{{ json_encode($payment->gateway_payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        @endif
    </div>
</x-app-layout>
