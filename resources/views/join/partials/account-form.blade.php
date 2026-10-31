@php
    // The no-JavaScript twin of /join/account: the same two states, the same
    // forms. Only rendered inside <noscript>.
    $c = $config;
    $copy = $c['copy'];
    $values = $c['values'];

    $cardClasses = 'survey-step rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800/80 dark:bg-slate-900 sm:p-6';
    $fieldClasses = 'mt-1 block w-full rounded-xl border-slate-300 bg-white text-base shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 sm:text-sm';
    $labelClasses = 'block text-sm font-semibold text-slate-700 dark:text-slate-300';

    $formId = $c['formId'];
    $submitLabel = $c['pending']
        ? $copy['verify']
        : ($c['otpEnabled'] ? $copy['sendMyCode'] : $copy['createMyAccount']);
    $mobileSubmitLabel = $c['pending']
        ? $copy['verifyShort']
        : ($c['otpEnabled'] ? $copy['sendCode'] : $copy['createAccount']);
@endphp

<x-onboarding-progress :current="$c['currentGroup']" :steps="$c['rail']"
                       :answered="$c['answered']" :questions="$c['questions']" :rail-units="$c['railUnits']" />

<div class="mt-5 grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start">
    <div class="space-y-5">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider text-primary">
            {{ $c['pending'] ? $copy['almostDone'] : $copy['lastStep'] }}
        </span>

        <h1 class="text-2xl font-bold leading-tight text-slate-900 dark:text-slate-100 sm:text-3xl">
            {{ $c['pending'] ? $copy['confirmTitle'] : $copy['createTitle'] }}
        </h1>

        <p class="max-w-2xl text-sm leading-relaxed text-slate-500 dark:text-slate-400">
            @if ($c['pending'])
                {{ __('Enter the :length-digit code we texted to :mobile. Your account is created as soon as it matches.', ['length' => $c['length'], 'mobile' => $c['mobile']]) }}
            @else
                {{ $c['otpEnabled'] ? $copy['otpOnBody'] : $copy['otpOffBody'] }}
            @endif
        </p>

        @if ($errors->any())
            <div class="flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300" role="alert">
                <div class="min-w-0">
                    <p class="font-semibold">{{ $copy['accountErrorTitle'] }}</p>
                    <ul class="mt-1 list-inside list-disc space-y-0.5">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif

        @if ($c['pending'])
            {{-- ── The code that finishes the sign-up ─────────────────────── --}}
            <div class="{{ $cardClasses }} space-y-5">
                @if ($c['status']['resent'])
                    <div class="rounded-xl border border-emerald-200/80 bg-emerald-50 p-3 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                        {{ $copy['otpResent'] }}
                    </div>
                @elseif ($c['status']['sendFailed'])
                    <div class="rounded-xl border border-amber-200/80 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300">
                        {{ $copy['otpSendFailed'] }}
                    </div>
                @endif

                <form id="{{ $formId }}" method="POST" action="{{ $c['urls']['verify'] }}" class="space-y-3">
                    @csrf

                    <label class="{{ $labelClasses }}" for="code">{{ $copy['codeLabel'] }}</label>
                    <input
                        id="code"
                        name="code"
                        type="text"
                        inputmode="numeric"
                        pattern="[0-9]*"
                        maxlength="{{ $c['length'] }}"
                        autocomplete="one-time-code"
                        required
                        autofocus
                        class="{{ $fieldClasses }} text-center text-2xl font-bold tracking-[0.5em]"
                    />
                    <x-input-error :messages="$errors->get('code')" class="mt-2" />

                    <p class="text-center text-xs text-slate-400">{{ $copy['codeHint'] }}</p>

                    <button type="submit"
                            class="inline-flex min-h-11 w-full items-center justify-center gap-2 rounded-xl bg-primary px-6 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 transition-colors hover:bg-primary/90">
                        {{ $copy['verify'] }}
                    </button>
                </form>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200/80 pt-4 dark:border-slate-800/80">
                    <form method="POST" action="{{ $c['urls']['resend'] }}">
                        @csrf

                        <button type="submit" class="text-sm font-semibold text-primary hover:underline">
                            {{ $copy['resend'] }}
                        </button>
                    </form>

                    <form method="POST" action="{{ $c['urls']['edit'] }}">
                        @csrf

                        <button type="submit" class="text-sm font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-100">
                            {{ $copy['changeDetails'] }}
                        </button>
                    </form>
                </div>

                <p class="text-xs text-slate-500 dark:text-slate-400">{{ $copy['resendNoScript'] }}</p>
            </div>
        @else
            {{-- ── Identity + password ────────────────────────────────────── --}}
            <form method="POST" action="{{ $c['urls']['store'] }}" id="{{ $formId }}" class="space-y-5">
                @csrf

                <div class="{{ $cardClasses }}">
                    <p class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                        {{ $copy['identityTitle'] }}
                    </p>

                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="{{ $labelClasses }}" for="first_name">{{ $copy['firstName'] }}</label>
                            <input id="first_name" name="first_name" type="text" maxlength="100" required autocomplete="given-name" enterkeyhint="next"
                                   value="{{ $values['first_name'] }}" class="{{ $fieldClasses }}" />
                            <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
                        </div>

                        <div>
                            <label class="{{ $labelClasses }}" for="last_name">{{ $copy['lastName'] }}</label>
                            <input id="last_name" name="last_name" type="text" maxlength="100" required autocomplete="family-name"
                                   value="{{ $values['last_name'] }}" class="{{ $fieldClasses }}" />
                            <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
                        </div>

                        <div class="sm:col-span-2">
                            <label class="{{ $labelClasses }}" for="email">{{ $copy['email'] }}</label>
                            <input id="email" name="email" type="email" maxlength="255" required autocomplete="username"
                                   value="{{ $values['email'] }}" class="{{ $fieldClasses }}" />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>

                        <div class="sm:col-span-2">
                            <label class="{{ $labelClasses }}" for="mobile">{{ $copy['mobileLabel'] }}</label>
                            <input id="mobile" name="mobile" type="tel" required inputmode="numeric" autocomplete="tel-national"
                                   placeholder="07X XXX XXX X" value="{{ $values['mobile'] }}" class="{{ $fieldClasses }}" />
                            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $copy['mobileHint'] }}</p>
                            <x-input-error :messages="$errors->get('mobile')" class="mt-2" />
                        </div>

                        <div>
                            <label class="{{ $labelClasses }}" for="password">{{ $copy['password'] }}</label>
                            <input id="password" name="password" type="password" required autocomplete="new-password"
                                   class="{{ $fieldClasses }}" />
                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                        </div>

                        <div>
                            <label class="{{ $labelClasses }}" for="password_confirmation">{{ $copy['passwordConfirmation'] }}</label>
                            <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                                   class="{{ $fieldClasses }}" />
                        </div>
                    </div>

                    <x-input-error :messages="$errors->get('invite')" class="mt-2" />
                </div>

                <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800/80 dark:bg-slate-900 sm:p-6">
                    <label class="flex cursor-pointer items-start gap-3 text-sm text-slate-600 dark:text-slate-300">
                        <input type="checkbox" name="agree" value="1" required @checked($values['agree'])
                               class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 text-primary shadow-sm focus:ring-primary dark:border-slate-600 dark:bg-slate-900" />
                        <span>
                            <span class="block font-semibold text-slate-800 dark:text-slate-100">{{ $copy['agreeTitle'] }}</span>
                            <span class="mt-1 block text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                                {{ $copy['agreePrefix'] }}
                                <a href="{{ $c['urls']['terms'] }}" target="_blank" rel="noopener" class="font-semibold text-primary hover:underline">{{ $copy['teacherTerms'] }}</a>
                                {{ $copy['agreeAnd'] }}
                                <a href="{{ $c['urls']['privacy'] }}" target="_blank" rel="noopener" class="font-semibold text-primary hover:underline">{{ $copy['privacy'] }}</a>{{ $copy['agreeSuffix'] }}
                            </span>
                        </span>
                    </label>
                    <x-input-error :messages="$errors->get('agree')" class="mt-2" />
                </div>

                <div class="rounded-2xl border border-primary/25 bg-primary/5 p-4 text-xs leading-relaxed text-slate-600 dark:border-primary/30 dark:bg-primary/10 dark:text-slate-300">
                    {{ $copy['officialNote'] }}
                </div>

                <div class="hidden items-center justify-between gap-3 border-t border-slate-200/80 pt-5 dark:border-slate-800/80 lg:flex">
                    <a href="{{ $c['urls']['back'] }}" class="text-sm font-semibold text-slate-500 transition-colors hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-100">
                        {{ $copy['back'] }}
                    </a>

                    <button type="submit"
                            class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-primary px-6 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 transition-colors hover:bg-primary/90">
                        {{ $submitLabel }}
                        <span aria-hidden="true">→</span>
                    </button>
                </div>
            </form>
        @endif
    </div>

    <x-survey.summary :groups="$c['summary']" :answered="$c['answered']" :questions="$c['questions']" />
</div>

<x-survey.mobile-actions :form="$formId"
                         :submit-label="$mobileSubmitLabel"
                         :back-url="$c['pending'] ? null : $c['urls']['back']" />
