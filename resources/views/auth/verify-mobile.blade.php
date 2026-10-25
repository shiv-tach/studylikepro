<x-guest-layout>
    <div class="mb-4">
        <h1 class="text-lg font-semibold text-slate-900 dark:text-slate-100">{{ __('Verify your mobile number') }}</h1>
        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
            {{ __('Enter the :length-digit code we texted to :mobile.', ['length' => $length, 'mobile' => $mobile]) }}
        </p>
    </div>

    @if (session('status') === 'otp-resent')
        <div class="mb-4 rounded-xl border border-emerald-200/80 bg-emerald-50 p-3 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
            {{ __('A new code is on its way.') }}
        </div>
    @elseif (session('status') === 'otp-send-failed')
        <div class="mb-4 rounded-xl border border-amber-200/80 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300">
            {{ __('We could not send the text just now. Please try again in a moment.') }}
        </div>
    @endif

    <form method="POST" action="{{ route('register.verify.store') }}">
        @csrf

        <x-input-label for="code" :value="__('Verification code')" />

        <x-text-input
            id="code"
            class="mt-1 block w-full text-center text-lg tracking-[0.5em]"
            type="text"
            name="code"
            inputmode="numeric"
            pattern="[0-9]*"
            maxlength="{{ $length }}"
            autocomplete="one-time-code"
            required
            autofocus
        />

        <x-input-error :messages="$errors->get('code')" class="mt-2" />

        <x-primary-button class="mt-4 w-full justify-center">
            {{ __('Verify and create account') }}
        </x-primary-button>
    </form>

    <div class="mt-6 flex flex-wrap items-center justify-between gap-3" x-data="otpResend({{ $cooldown }})">
        <form method="POST" action="{{ route('register.verify.resend') }}">
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

        <form method="POST" action="{{ route('register.cancel') }}">
            @csrf

            <button type="submit" class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100">
                {{ __('Use a different number') }}
            </button>
        </form>
    </div>

    <noscript>
        <p class="mt-4 text-xs text-gray-500 dark:text-gray-400">
            {{ __('The resend button works without JavaScript; it can be used once the cooldown has passed.') }}
        </p>
    </noscript>
</x-guest-layout>
