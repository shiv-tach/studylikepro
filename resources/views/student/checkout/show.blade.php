@php
    use App\Enums\PaymentStatus;

    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $timezone = auth()->user()->studentProfile?->timezone ?? config('studylikepro.default_display_timezone');
    $paid = $payment->status->isSettled();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('student.bookings.show', $booking) }}" class="text-xs font-semibold text-slate-400 transition-colors hover:text-primary">&larr; {{ __('Back to the lesson') }}</a>
            <h2 class="mt-1 font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Checkout') }}</h2>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-6">
        @if (session('status') === 'payment-pending')
            <div class="rounded-2xl border border-amber-200/80 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300">
                {{ __('We are still waiting for the payment to reach us. Refresh in a moment — nothing has been charged twice.') }}
            </div>
        @endif

        @if ($errors->has('status'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
                {{ $errors->first('status') }}
            </div>
        @endif

        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Lesson') }}</p>
                    <p class="mt-1 text-lg font-bold text-slate-800 dark:text-slate-100">
                        {{ $booking->subject?->name }}@if ($booking->lesson) · {{ $booking->lesson->name }}@endif
                    </p>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ $booking->starts_at->copy()->setTimezone($timezone)->format('D d M Y, H:i') }}–{{ $booking->ends_at->copy()->setTimezone($timezone)->format('H:i') }}
                        · {{ $booking->durationMinutes() }} {{ __('minutes') }}
                    </p>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ __('with :teacher', ['teacher' => $booking->teacherProfile->user->name]) }}
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Amount') }}</p>
                    <p class="mt-1 text-2xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100">{{ platform_settings()->formatMinor($payment->amount_minor) }}</p>
                    <p class="mt-1 text-xs text-slate-400">{{ $payment->gateway }}</p>
                </div>
            </div>

            <dl class="mt-5 space-y-2 border-t border-slate-200 pt-5 text-sm dark:border-slate-800">
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-slate-500 dark:text-slate-400">{{ __('Lesson') }}</dt>
                    <dd class="font-semibold text-slate-800 dark:text-slate-100">{{ platform_settings()->formatMinor($booking->price_minor) }}</dd>
                </div>
                @if ($booking->booking_fee_minor > 0)
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500 dark:text-slate-400">
                            {{ __('Platform booking fee') }}
                            @if ($booking->bookingFeePromotion)
                                <span class="text-emerald-600 dark:text-emerald-400">· {{ $booking->bookingFeePromotion->name }}</span>
                            @endif
                        </dt>
                        <dd class="{{ $booking->hasBookingFeeDiscount() ? 'font-semibold text-emerald-600 dark:text-emerald-400' : 'font-semibold text-slate-800 dark:text-slate-100' }}">
                            @if ($booking->hasBookingFeeDiscount())
                                {{ platform_settings()->formatMinor($booking->netBookingFeeMinor()) }}
                                <span class="ml-1 text-xs font-normal text-slate-400 line-through">{{ platform_settings()->formatMinor($booking->booking_fee_minor) }}</span>
                            @else
                                {{ platform_settings()->formatMinor($booking->booking_fee_minor) }}
                            @endif
                        </dd>
                    </div>
                @endif
                <div class="flex items-center justify-between gap-3 border-t border-slate-100 pt-2 dark:border-slate-800">
                    <dt class="font-semibold text-slate-600 dark:text-slate-300">{{ __('Total due') }}</dt>
                    <dd class="text-base font-bold text-slate-900 dark:text-slate-100">{{ platform_settings()->formatMinor($payment->amount_minor) }}</dd>
                </div>
            </dl>

            <dl class="mt-5 grid gap-3 border-t border-slate-200 pt-5 text-sm sm:grid-cols-2 dark:border-slate-800">
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-slate-500 dark:text-slate-400">{{ __('Order') }}</dt>
                    <dd class="font-mono text-xs text-slate-600 dark:text-slate-300">{{ $payment->gateway_order_id }}</dd>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <dt class="text-slate-500 dark:text-slate-400">{{ __('Status') }}</dt>
                    <dd><span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $payment->status->badgeClasses() }}">{{ $payment->status->label() }}</span></dd>
                </div>
            </dl>

            @if ($paid)
                <div class="mt-5 rounded-xl border border-emerald-200/80 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                    {{ __('Payment received and the lesson is confirmed.') }}
                </div>
                <div class="mt-4 flex flex-wrap gap-3">
                    <a href="{{ route('student.bookings.show', $booking) }}"
                       class="inline-flex items-center rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary/90">
                        {{ __('Open the lesson') }}
                    </a>
                    <a href="{{ route('receipts.show', $booking) }}"
                       class="inline-flex items-center rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                        {{ __('Receipt') }}
                    </a>
                </div>
            @elseif ($isLocalGateway)
                <div class="mt-5 rounded-xl border border-dashed border-slate-300 p-4 dark:border-slate-700">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Demo gateway') }}</p>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ __('The provider is running in offline mode, so these buttons post a signed provider webhook through the real endpoint — signature check, idempotency store and all.') }}
                    </p>
                    <div class="mt-4 flex flex-wrap gap-3">
                        <form method="POST" action="{{ route('payments.webhook', 'fake') }}" data-demo-payment
                              data-redirect="{{ route('student.bookings.show', $booking) }}">
                            <input type="hidden" name="payload" value="{{ $localCapture['payload'] }}">
                            <input type="hidden" name="signature" value="{{ $localCapture['signature'] }}">
                            <x-primary-button>{{ __('Pay :amount', ['amount' => platform_settings()->formatMinor($payment->amount_minor)]) }}</x-primary-button>
                        </form>
                        <form method="POST" action="{{ route('payments.webhook', 'fake') }}" data-demo-payment
                              data-redirect="{{ route('student.bookings.show', $booking) }}">
                            <input type="hidden" name="payload" value="{{ $localFailure['payload'] }}">
                            <input type="hidden" name="signature" value="{{ $localFailure['signature'] }}">
                            <x-secondary-button>{{ __('Simulate a declined card') }}</x-secondary-button>
                        </form>
                    </div>
                </div>
                <script>
                    document.querySelectorAll('form[data-demo-payment]').forEach((form) => {
                        form.addEventListener('submit', async (event) => {
                            event.preventDefault();
                            const button = form.querySelector('button');
                            button.disabled = true;
                            await fetch(form.action, {
                                method: 'POST',
                                body: new FormData(form),
                                headers: { Accept: 'application/json' },
                            });
                            window.location.href = form.dataset.redirect;
                        });
                    });
                </script>
            @else
                <div class="mt-5">
                    <button id="razorpay-pay" type="button"
                            class="inline-flex items-center rounded-xl bg-primary px-4 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 transition hover:bg-primary/90">
                        {{ __('Pay :amount', ['amount' => platform_settings()->formatMinor($payment->amount_minor)]) }}
                    </button>
                    <p class="mt-2 text-xs text-slate-400">{{ __('You will be redirected to the secure Razorpay checkout.') }}</p>
                </div>
            @endif
        </div>

        @unless ($paid || $isLocalGateway)
            <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
            <script>
                document.getElementById('razorpay-pay').addEventListener('click', function () {
                    const razorpay = new Razorpay(@js($checkout));
                    razorpay.on('payment.failed', function (response) {
                        window.location.href = @js(route('student.bookings.show', $booking));
                    });
                    razorpay.open();
                });
            </script>
        @endunless
    </div>
</x-app-layout>
