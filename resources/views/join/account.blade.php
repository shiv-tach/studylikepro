@php
    // The last step of the invite survey, in two states on one URL:
    //  - no code yet  → identity + password (the account does not exist),
    //  - code sent    → the SMS passcode, which is what actually creates it.
    $cardClasses = 'survey-step rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800/80 dark:bg-slate-900 sm:p-6';
    $fieldClasses = 'mt-1 block w-full rounded-xl border-slate-300 bg-white text-base shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 sm:text-sm';
    $labelClasses = 'block text-sm font-semibold text-slate-700 dark:text-slate-300';
    $primaryButton = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-primary px-6 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 transition-colors hover:bg-primary/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 disabled:cursor-not-allowed disabled:opacity-60';
    $formId = $pending ? 'join-verify-form' : 'join-account-form';
    $submitLabel = $otpEnabled ? __('Send my code') : __('Create my account');
    // The phone bar is narrower than the inline button, so it gets a short label.
    $mobileSubmitLabel = $pending
        ? __('Verify code')
        : ($otpEnabled ? __('Send code') : __('Create account'));
    $answeredCount = (int) $answered;
    $questionTotal = (int) $questions;
@endphp

<x-onboarding-layout :guest="$guest">
    <x-slot name="progress">
        <x-onboarding-progress :current="$currentStep" :steps="$steps"
                               :answered="$answeredCount" :questions="$questionTotal" />
    </x-slot>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_20rem] lg:items-start">
        <div class="space-y-5">
            <div class="survey-enter">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider text-primary">
                    {{ $pending ? __('Almost done') : __('Last step') }}
                </span>

                <h1 class="mt-2 text-2xl font-bold leading-tight text-slate-900 dark:text-slate-100 sm:text-3xl">
                    {{ $pending ? __('Confirm your mobile number') : __('Create your account') }}
                </h1>
                <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                    @if ($pending)
                        {{ __('Enter the :length-digit code we texted to :mobile. Your account is created as soon as it matches.', ['length' => $length, 'mobile' => $mobile]) }}
                    @else
                        {{ $otpEnabled
                            ? __('We text a code to your mobile number. Your account — with everything you just filled in — is created once you confirm it.')
                            : __('Your account is created right away, with everything you just filled in.') }}
                    @endif
                </p>
            </div>

            @if ($errors->any())
                <div class="survey-enter flex items-start gap-3 rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300" role="alert">
                    <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-rose-500 text-white" aria-hidden="true">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v4m0 4h.01" />
                        </svg>
                    </span>

                    <div class="min-w-0">
                        <p class="font-semibold">{{ __('Please fix the following:') }}</p>
                        <ul class="mt-1 list-inside list-disc space-y-0.5">
                            @foreach ($errors->all() as $message)
                                <li>{{ $message }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            @if ($pending)
                {{-- ── The code that finishes the sign-up ─────────────────────── --}}
                <div class="{{ $cardClasses }} space-y-5">
                    @if (session('status') === 'otp-resent')
                        <div class="rounded-xl border border-emerald-200/80 bg-emerald-50 p-3 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                            {{ __('A new code is on its way.') }}
                        </div>
                    @elseif (session('status') === 'otp-send-failed')
                        <div class="rounded-xl border border-amber-200/80 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300">
                            {{ __('We could not send the text just now. Please try again in a moment.') }}
                        </div>
                    @endif

                    <form id="{{ $formId }}" method="POST" action="{{ route('join.account.verify') }}"
                          x-data="submitOnce" x-on:submit="guard($event)"
                          class="space-y-3">
                        @csrf

                        <label class="{{ $labelClasses }}" for="code">{{ __('Verification code') }}</label>
                        <input
                            id="code"
                            name="code"
                            type="text"
                            inputmode="numeric"
                            pattern="[0-9]*"
                            maxlength="{{ $length }}"
                            autocomplete="one-time-code"
                            required
                            autofocus
                            class="{{ $fieldClasses }} text-center text-2xl font-bold tracking-[0.5em]"
                        />
                        <x-input-error :messages="$errors->get('code')" class="mt-2" />

                        <p class="text-center text-xs text-slate-400">
                            {{ __('Codes expire after a few minutes. If it does not arrive, send a new one below.') }}
                        </p>

                        <button type="submit" class="{{ $primaryButton }} w-full" :disabled="busy">
                            <span class="inline-flex items-center gap-2" x-show="! busy">{{ __('Verify and create my account') }}</span>
                            <span class="inline-flex items-center gap-2" x-show="busy" x-cloak>
                                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-90" fill="currentColor" d="M21 12a9 9 0 0 1-9 9v-4a5 5 0 0 0 5-5h4z" />
                                </svg>
                                {{ __('Checking…') }}
                            </span>
                        </button>
                    </form>

                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200/80 pt-4 dark:border-slate-800/80" x-data="otpResend({{ $cooldown }})">
                        <form method="POST" action="{{ route('join.account.resend') }}">
                            @csrf

                            <button
                                type="submit"
                                class="text-sm font-semibold text-primary hover:underline disabled:cursor-not-allowed disabled:text-slate-400 dark:disabled:text-slate-500"
                                :disabled="seconds > 0"
                            >
                                <span x-show="seconds <= 0" x-cloak>{{ __('Send a new code') }}</span>
                                <span x-show="seconds > 0" x-cloak x-text="`{{ __('Resend in') }} ${seconds}s`"></span>
                            </button>
                        </form>

                        <form method="POST" action="{{ route('join.account.edit') }}">
                            @csrf

                            <button type="submit" class="text-sm font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-100">
                                {{ __('Change my details') }}
                            </button>
                        </form>
                    </div>

                    <noscript>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ __('The resend button works without JavaScript; it can be used once the cooldown has passed.') }}
                        </p>
                    </noscript>
                </div>
            @else
                {{-- ── Identity + password ────────────────────────────────────── --}}
                <form method="POST" action="{{ route('join.account.store') }}" id="{{ $formId }}"
                      x-data="submitOnce" x-on:submit="guard($event)"
                      class="space-y-5">
                    @csrf

                    <div class="{{ $cardClasses }}">
                        <p class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">
                            {{ __('How students reach you') }}
                        </p>

                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="{{ $labelClasses }}" for="first_name">{{ __('First name') }}</label>
                                <input id="first_name" name="first_name" type="text" maxlength="100" required autocomplete="given-name" enterkeyhint="next"
                                       value="{{ old('first_name') }}" class="{{ $fieldClasses }}" />
                                <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
                            </div>

                            <div>
                                <label class="{{ $labelClasses }}" for="last_name">{{ __('Last name') }}</label>
                                <input id="last_name" name="last_name" type="text" maxlength="100" required autocomplete="family-name"
                                       value="{{ old('last_name') }}" class="{{ $fieldClasses }}" />
                                <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
                            </div>

                            <div class="sm:col-span-2">
                                <label class="{{ $labelClasses }}" for="email">{{ __('Email') }}</label>
                                <input id="email" name="email" type="email" maxlength="255" required autocomplete="username"
                                       value="{{ old('email') }}" class="{{ $fieldClasses }}" />
                                <x-input-error :messages="$errors->get('email')" class="mt-2" />
                            </div>

                            <div class="sm:col-span-2">
                                <label class="{{ $labelClasses }}" for="mobile">{{ __('Mobile number') }}</label>
                                <input id="mobile" name="mobile" type="tel" required inputmode="numeric" autocomplete="tel-national"
                                       placeholder="07X XXX XXX X" value="{{ old('mobile') }}" class="{{ $fieldClasses }}" />
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                                    {{ __('Sri Lankan mobile, e.g. 07X XXX XXX X. You can sign in with it later.') }}
                                </p>
                                <x-input-error :messages="$errors->get('mobile')" class="mt-2" />
                            </div>

                            <div>
                                <label class="{{ $labelClasses }}" for="password">{{ __('Password') }}</label>
                                <input id="password" name="password" type="password" required autocomplete="new-password"
                                       class="{{ $fieldClasses }}" />
                                <x-input-error :messages="$errors->get('password')" class="mt-2" />
                            </div>

                            <div>
                                <label class="{{ $labelClasses }}" for="password_confirmation">{{ __('Confirm password') }}</label>
                                <input id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                                       class="{{ $fieldClasses }}" />
                            </div>
                        </div>

                        <x-input-error :messages="$errors->get('invite')" class="mt-2" />
                    </div>

                    {{-- The same acceptance the verification step asks for, moved to
                         the moment the account is registered. --}}
                    <div class="rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800/80 dark:bg-slate-900 sm:p-6">
                        <label class="flex cursor-pointer items-start gap-3 text-sm text-slate-600 dark:text-slate-300">
                            <input type="checkbox" name="agree" value="1" required @checked(old('agree'))
                                   class="mt-0.5 h-4 w-4 shrink-0 rounded border-slate-300 text-primary shadow-sm focus:ring-primary dark:border-slate-600 dark:bg-slate-900" />
                            <span>
                                <span class="block font-semibold text-slate-800 dark:text-slate-100">{{ __('Terms and policies accepted') }}</span>
                                <span class="mt-1 block text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                                    {{ __('I have read and accept the') }}
                                    <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener" class="font-semibold text-primary hover:underline">{{ __('teacher terms') }}</a>
                                    {{ __('and the') }}
                                    <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener" class="font-semibold text-primary hover:underline">{{ __('privacy policy') }}</a>{{ __(', including the commission and cancellation rules.') }}
                                </span>
                            </span>
                        </label>
                        <x-input-error :messages="$errors->get('agree')" class="mt-2" />
                    </div>

                    <div class="rounded-2xl border border-primary/25 bg-primary/5 p-4 text-xs leading-relaxed text-slate-600 dark:border-primary/30 dark:bg-primary/10 dark:text-slate-300">
                        {{ __('It’s official only after this: the account keeps the answers you just gave. Right after signing in, two short steps finish your profile — headline and bio, then your photo and the exact hours you teach.') }}
                    </div>

                    <div class="hidden items-center justify-between gap-3 border-t border-slate-200/80 pt-5 dark:border-slate-800/80 lg:flex">
                        <a href="{{ route('join.proof') }}" class="text-sm font-semibold text-slate-500 transition-colors hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-100">
                            {{ __('Back') }}
                        </a>

                        <button type="submit" class="{{ $primaryButton }}" :disabled="busy">
                            <span class="inline-flex items-center gap-2" x-show="! busy">
                                {{ $submitLabel }}
                                <span aria-hidden="true">→</span>
                            </span>
                            <span class="inline-flex items-center gap-2" x-show="busy" x-cloak>
                                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                                    <circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="4" />
                                    <path class="opacity-90" fill="currentColor" d="M21 12a9 9 0 0 1-9 9v-4a5 5 0 0 0 5-5h4z" />
                                </svg>
                                {{ __('Saving…') }}
                            </span>
                        </button>
                    </div>
                </form>
            @endif
        </div>

        <x-survey.summary :groups="$summary" :answered="$answeredCount" :questions="$questionTotal" />
    </div>

    <x-survey.mobile-actions :form="$formId"
                             :submit-label="$mobileSubmitLabel"
                             :back-url="$pending ? null : route('join.proof')" />
</x-onboarding-layout>
