@php
    $money = platform_settings()->formatMinor($payment->amount_minor);
    $cardMethod = collect($manual['methods'])->firstWhere('value', 'gateway');
    $cardAvailable = ($cardMethod['enabled'] ?? false) || $isLocalGateway;
    // Locally the offline gateway is a deliberate demo affordance, so the card
    // panel opens straight away; in production the student has to pick a method.
    $activeMethod = $manual['selected'] ?? ($isLocalGateway ? 'gateway' : null);
    $selectedMethod = collect($manual['methods'])->firstWhere('value', $activeMethod);
    $chosenAccount = collect($manual['accounts'])->firstWhere('id', $payment->bank_account_id);
    $methodCard = 'flex h-full w-full flex-col gap-1 rounded-2xl border p-4 text-left transition';
    $methodCardActive = 'border-primary bg-primary/5 dark:border-primary';
    $methodCardIdle = 'border-slate-200 bg-white hover:border-primary/60 dark:border-slate-800 dark:bg-slate-900';
    $methodCardDisabled = 'cursor-not-allowed border-slate-200 bg-slate-50 opacity-70 dark:border-slate-800 dark:bg-slate-900/40';
@endphp

<div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Choose how to pay') }}</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ __('Bank transfers and deposits are checked by our team before the payment is confirmed.') }}
            </p>
        </div>
        <p class="text-right">
            <span class="block text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Amount due') }}</span>
            <span class="mt-1 block text-xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100">{{ $money }}</span>
        </p>
    </div>

    @if ($errors->has('method'))
        <p class="mt-3 rounded-xl bg-rose-50 p-3 text-sm text-rose-700 dark:bg-rose-950/30 dark:text-rose-300">{{ $errors->first('method') }}</p>
    @endif

    @if (empty($manual['accounts']) && ! $manual['isSubmitted'])
        <p class="mt-3 rounded-xl bg-amber-50 p-3 text-sm text-amber-800 dark:bg-amber-950/30 dark:text-amber-300">
            {{ __('Bank details are not set up yet — please contact support before sending any money.') }}
        </p>
    @endif

    <div class="mt-4 grid gap-3 sm:grid-cols-3">
        @foreach ($manual['methods'] as $method)
            @php
                $isGateway = $method['value'] === 'gateway';
                $available = $isGateway ? $cardAvailable : $method['enabled'];
                $isActive = $activeMethod === $method['value'];
                $classes = $isActive ? $methodCardActive : ($available ? $methodCardIdle : $methodCardDisabled);
            @endphp

            <form method="POST" action="{{ route('student.payments.method', $payment) }}" class="block">
                @csrf
                <input type="hidden" name="method" value="{{ $method['value'] }}">
                <button type="submit" class="{{ $methodCard }} {{ $classes }}"
                        @disabled(! $available || $manual['isSubmitted'])>
                    <span class="flex items-center justify-between gap-2">
                        <span class="text-sm font-bold {{ $available ? 'text-slate-800 dark:text-slate-100' : 'text-slate-500 dark:text-slate-400' }}">{{ $method['label'] }}</span>
                        @if ($isActive)
                            <span class="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-semibold text-primary">{{ __('Selected') }}</span>
                        @elseif ($isGateway && ! $cardAvailable)
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-300">{{ __('Coming soon') }}</span>
                        @endif
                    </span>
                    <span class="text-xs leading-relaxed {{ $available ? 'text-slate-500 dark:text-slate-400' : 'text-slate-400 dark:text-slate-500' }}">{{ $method['description'] }}</span>
                </button>
            </form>
        @endforeach
    </div>

    @if ($manual['isSubmitted'])
        <p class="mt-3 text-xs text-slate-400">{{ __('Your slip is already with our team, so the method is locked until they check it.') }}</p>
    @endif
</div>

@if ($manual['isSubmitted'])
    <div class="mt-6 rounded-2xl border border-sky-200/80 bg-sky-50/70 p-6 dark:border-sky-900/40 dark:bg-sky-950/20">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-base font-bold text-sky-900 dark:text-sky-200">{{ __('Your slip is with our team') }}</h3>
                <p class="mt-1 text-sm text-sky-800/80 dark:text-sky-300/80">
                    {{ __('We are checking the proof you uploaded. You do not need to pay again — we will confirm the payment as soon as it matches.') }}
                </p>
            </div>
            <span class="rounded-full bg-sky-100 px-2.5 py-0.5 text-[11px] font-semibold text-sky-700 dark:bg-sky-900/40 dark:text-sky-300">{{ __('Awaiting verification') }}</span>
        </div>

        <dl class="mt-4 grid gap-3 border-t border-sky-200/70 pt-4 text-sm sm:grid-cols-2 dark:border-sky-900/40">
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-sky-700/70 dark:text-sky-400/80">{{ __('Method') }}</dt>
                <dd class="mt-1 font-semibold text-slate-800 dark:text-slate-100">{{ $manual['selectedLabel'] }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-sky-700/70 dark:text-sky-400/80">{{ __('Amount') }}</dt>
                <dd class="mt-1 font-semibold text-slate-800 dark:text-slate-100">{{ $money }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-sky-700/70 dark:text-sky-400/80">{{ __('Paid into') }}</dt>
                <dd class="mt-1 text-slate-700 dark:text-slate-200">{{ $chosenAccount['bankName'] ?? __('Bank account') }} @if ($chosenAccount) · <span class="font-mono text-xs">{{ $chosenAccount['accountNumber'] }}</span> @endif</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-sky-700/70 dark:text-sky-400/80">{{ __('Reference') }}</dt>
                <dd class="mt-1 font-mono text-xs text-slate-700 dark:text-slate-200">{{ $manual['bankReference'] ?: '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-sky-700/70 dark:text-sky-400/80">{{ __('Submitted') }}</dt>
                <dd class="mt-1 text-slate-700 dark:text-slate-200">{{ $manual['submittedAtLabel'] ?? '—' }}</dd>
            </div>
            <div>
                <dt class="text-xs font-semibold uppercase tracking-wider text-sky-700/70 dark:text-sky-400/80">{{ __('Slip') }}</dt>
                <dd class="mt-1">
                    @if ($manual['slipUrl'])
                        <a href="{{ $manual['slipUrl'] }}" target="_blank" rel="noopener" class="font-semibold text-primary hover:underline">
                            {{ $manual['slipName'] ?: __('View the uploaded slip') }}
                        </a>
                    @else
                        <span class="text-slate-400">—</span>
                    @endif
                </dd>
            </div>
        </dl>

        @if ($manual['holdUntilLabel'])
            <p class="mt-4 rounded-xl bg-white/70 px-3 py-2 text-xs text-sky-900 dark:bg-slate-900/40 dark:text-sky-200">
                {{ __('Your slot stays reserved until :time while we check the payment.', ['time' => $manual['holdUntilLabel']]) }}
            </p>
        @endif

        <details class="mt-4">
            <summary class="cursor-pointer text-sm font-semibold text-primary hover:underline">{{ __('Upload a different slip') }}</summary>
            @include('student.checkout.partials.slip-form', ['submitLabel' => __('Replace the slip')])
        </details>
    </div>
@elseif ($activeMethod === 'gateway' && $cardAvailable)
    <div class="{{ $cardBase }} mt-6" :class="{{ $cardTheme }}">
        @if ($isLocalGateway)
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Demo gateway') }}</p>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ __('The provider is running in offline mode, so these buttons post a signed provider webhook through the real endpoint — signature check, idempotency store and all.') }}
            </p>
            <div class="mt-4 flex flex-wrap gap-3">
                <form method="POST" action="{{ route('payments.webhook', 'fake') }}" data-demo-payment
                      data-redirect="{{ $demoReturnUrl }}">
                    @csrf
                    <input type="hidden" name="payload" value="{{ $localCapture['payload'] }}">
                    <input type="hidden" name="signature" value="{{ $localCapture['signature'] }}">
                    <x-primary-button>{{ __('Pay :amount', ['amount' => $money]) }}</x-primary-button>
                </form>
                <form method="POST" action="{{ route('payments.webhook', 'fake') }}" data-demo-payment
                      data-redirect="{{ $demoReturnUrl }}">
                    @csrf
                    <input type="hidden" name="payload" value="{{ $localFailure['payload'] }}">
                    <input type="hidden" name="signature" value="{{ $localFailure['signature'] }}">
                    <x-secondary-button>{{ __('Simulate a declined card') }}</x-secondary-button>
                </form>
            </div>
        @else
            <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Pay by card') }}</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('You will be redirected to the secure card checkout.') }}</p>
            <button id="razorpay-pay" type="button"
                    class="mt-4 inline-flex items-center rounded-xl bg-primary px-4 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 transition hover:bg-primary/90">
                {{ __('Pay :amount', ['amount' => $money]) }}
            </button>
        @endif
    </div>
@elseif ($selectedMethod && $selectedMethod['requiresSlip'])
    <div class="{{ $cardBase }} mt-6" :class="{{ $cardTheme }}">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ $selectedMethod['label'] }}</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $selectedMethod['instructions'] }}</p>
            </div>
            <span class="rounded-full bg-primary/10 px-2.5 py-0.5 text-[11px] font-semibold text-primary">{{ __('Step 2 of 2') }}</span>
        </div>

        @if ($manual['isRejected'])
            <div class="mt-4 rounded-xl border border-rose-200 bg-rose-50 p-3 text-sm text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
                <p class="font-semibold">{{ __('We could not verify your last slip.') }}</p>
                @if ($manual['failureReason'])
                    <p class="mt-1">{{ $manual['failureReason'] }}</p>
                @endif
                <p class="mt-1">{{ __('Upload a clearer photo or the receipt from the bank to try again.') }}</p>
            </div>
        @endif

        <div class="mt-4 grid gap-3 rounded-xl bg-slate-50 p-4 text-sm dark:bg-slate-800/40 sm:grid-cols-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Amount to pay') }}</p>
                <p class="mt-1 font-bold text-slate-900 dark:text-slate-100">{{ $money }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Use the reference') }}</p>
                <p class="mt-1 font-mono text-xs text-slate-700 dark:text-slate-200">{{ $manual['suggestedReference'] }}</p>
            </div>
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Your slot') }}</p>
                <p class="mt-1 text-xs text-slate-600 dark:text-slate-300">
                    {{ $manual['holdUntilLabel'] ? __('Reserved until :time', ['time' => $manual['holdUntilLabel']]) : __('Reserved while you pay') }}
                </p>
            </div>
        </div>

        @if ($selectedMethod['holdLabel'])
            <p class="mt-2 text-xs text-slate-400">{{ $selectedMethod['holdLabel'] }}</p>
        @endif

        @include('student.checkout.partials.slip-form', [
            'submitLabel' => $manual['isRejected'] ? __('Upload a new slip') : __('Submit the slip'),
        ])
    </div>
@else
    <div class="mt-6 rounded-2xl border border-dashed border-slate-300 p-4 text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
        {{ __('Pick a payment method above to see the bank details and the slip upload.') }}
    </div>
@endif

@if ($activeMethod === 'gateway' && $cardAvailable && ! $isLocalGateway)
    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        document.getElementById('razorpay-pay').addEventListener('click', function () {
            const razorpay = new Razorpay(@js($checkout));
            razorpay.on('payment.failed', function (response) {
                window.location.href = @js($demoReturnUrl);
            });
            razorpay.open();
        });
    </script>
@endif

@if ($isLocalGateway && $activeMethod === 'gateway' && $cardAvailable)
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
@endif
