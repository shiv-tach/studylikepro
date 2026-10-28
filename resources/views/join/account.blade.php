@php
    // Step 5 of the invite wizard, in two states on one URL:
    //  - no code yet  → identity + password (the account does not exist),
    //  - code sent    → the SMS passcode, which is what actually creates it.
    $cardClasses = 'rounded-2xl border border-slate-200/80 bg-white p-5 dark:border-slate-800/80 dark:bg-slate-900 sm:p-6';
    $fieldClasses = 'mt-1 block w-full rounded-xl border-slate-300 bg-white text-base shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 sm:text-sm';
    $labelClasses = 'block text-sm font-semibold text-slate-700 dark:text-slate-300';
@endphp
<x-onboarding-layout :guest="$guest">
    <x-slot name="progress">
        <x-onboarding-progress :current="$currentStep" :steps="$steps" />
    </x-slot>

    <div class="space-y-5">
        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
                <p class="font-semibold">{{ __('Please fix the following:') }}</p>
                <ul class="mt-1 list-inside list-disc space-y-0.5">
                    @foreach ($errors->all() as $message)
                        <li>{{ $message }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if ($pending)
            {{-- ── The code that finishes the sign-up ─────────────────────────── --}}
            <div class="{{ $cardClasses }}">
                <p class="text-[11px] font-bold uppercase tracking-wider text-primary">{{ __('Step 5 · Almost done') }}</p>
                <h1 class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">{{ __('Confirm your mobile number') }}</h1>
                <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                    {{ __('Enter the :length-digit code we texted to :mobile. Your account is created as soon as it matches.', ['length' => $length, 'mobile' => $mobile]) }}
                </p>

                @if (session('status') === 'otp-resent')
                    <div class="mt-4 rounded-xl border border-emerald-200/80 bg-emerald-50 p-3 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                        {{ __('A new code is on its way.') }}
                    </div>
                @elseif (session('status') === 'otp-send-failed')
                    <div class="mt-4 rounded-xl border border-amber-200/80 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300">
                        {{ __('We could not send the text just now. Please try again in a moment.') }}
                    </div>
                @endif

                <form method="POST" action="{{ route('join.account.verify') }}" class="mt-5">
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
                        class="{{ $fieldClasses }} text-center text-lg tracking-[0.5em]"
                    />
                    <x-input-error :messages="$errors->get('code')" class="mt-2" />

                    <x-primary-button class="mt-4 w-full justify-center">
                        {{ __('Verify and create my account') }}
                    </x-primary-button>
                </form>

                <div class="mt-5 flex flex-wrap items-center justify-between gap-3" x-data="otpResend({{ $cooldown }})">
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
                    <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                        {{ __('The resend button works without JavaScript; it can be used once the cooldown has passed.') }}
                    </p>
                </noscript>
            </div>
        @else
            {{-- ── Identity + password ────────────────────────────────────────── --}}
            <form method="POST" action="{{ route('join.account.store') }}" class="space-y-5">
                @csrf

                <div class="{{ $cardClasses }}">
                    <p class="text-[11px] font-bold uppercase tracking-wider text-primary">{{ __('Step 5 · Last step') }}</p>
                    <h1 class="mt-1 text-xl font-bold text-slate-900 dark:text-slate-100">{{ __('Create your account') }}</h1>
                    <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                        {{ $otpEnabled
                            ? __('We text a code to your mobile number. Your account — with everything you just filled in — is created once you confirm it.')
                            : __('Your account is created right away, with everything you just filled in.') }}
                    </p>

                    <div class="mt-5 grid gap-4 sm:grid-cols-2">
                        <div>
                            <label class="{{ $labelClasses }}" for="first_name">{{ __('First name') }}</label>
                            <input id="first_name" name="first_name" type="text" maxlength="100" required autocomplete="given-name"
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

                <div class="rounded-2xl border border-slate-200/80 bg-white p-4 text-xs leading-relaxed text-slate-500 dark:border-slate-800/80 dark:bg-slate-900 dark:text-slate-400">
                    {{ __('It’s official only after this: signing in here creates the account that keeps your profile, weekly hours and photo — then you finish with your ID and subjects.') }}
                </div>

                <div class="flex items-center justify-between gap-3">
                    <a href="{{ route('join.availability') }}" class="text-sm font-semibold text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-100">
                        {{ __('Back') }}
                    </a>
                    <x-primary-button>{{ $otpEnabled ? __('Send my code') : __('Create my account') }}</x-primary-button>
                </div>
            </form>
        @endif
    </div>
</x-onboarding-layout>
