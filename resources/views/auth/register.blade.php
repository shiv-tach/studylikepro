<x-guest-layout>
    @php
        $isInvite = ! is_null($invite);
    @endphp

    <form method="POST" action="{{ route('register') }}">
        @csrf

        @if ($isInvite)
            <div class="mb-4 rounded-2xl border border-primary/30 bg-primary/10 p-4 text-sm text-primary dark:border-primary/40 dark:bg-primary/10">
                You've been invited to join as a teacher. Complete the form below to create your account.
            </div>

            <input type="hidden" name="role" value="teacher" />
            <input type="hidden" name="invite" value="{{ $inviteToken }}" />
        @endif

        @if ($inviteError)
            <div class="mb-4 rounded-2xl border border-rose-200/80 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
                {{ $inviteError }}
            </div>
        @endif

        <!-- Name -->
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <x-input-label for="first_name" :value="__('First name')" />
                <x-text-input id="first_name" class="mt-1 block w-full" type="text" name="first_name" :value="old('first_name')" required autofocus autocomplete="given-name" />
                <x-input-error :messages="$errors->get('first_name')" class="mt-2" />
            </div>

            <div>
                <x-input-label for="last_name" :value="__('Last name')" />
                <x-text-input id="last_name" class="mt-1 block w-full" type="text" name="last_name" :value="old('last_name')" required autocomplete="family-name" />
                <x-input-error :messages="$errors->get('last_name')" class="mt-2" />
            </div>
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Mobile number -->
        <div class="mt-4">
            <x-input-label for="mobile" :value="__('Mobile number')" />
            <x-text-input id="mobile" class="block mt-1 w-full" type="tel" name="mobile" :value="old('mobile')" required inputmode="numeric" autocomplete="tel-national" placeholder="07X XXX XXX X" />
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Sri Lankan mobile, e.g. 07X XXX XXX X. We text a code to this number to confirm it, and you can sign in with it.') }}</p>
            <x-input-error :messages="$errors->get('mobile')" class="mt-2" />
        </div>

        <x-input-error :messages="$errors->get('role')" class="mt-2" />

        <x-input-error :messages="$errors->get('invite')" class="mt-2" />

        <!-- Password -->
        <div class="mt-4">
            <x-input-label for="password" :value="__('Password')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <!-- Confirm Password -->
        <div class="mt-4">
            <x-input-label for="password_confirmation" :value="__('Confirm Password')" />

            <x-text-input id="password_confirmation" class="block mt-1 w-full"
                            type="password"
                            name="password_confirmation" required autocomplete="new-password" />

            <x-input-error :messages="$errors->get('password_confirmation')" class="mt-2" />
        </div>

        <div class="flex items-center justify-end mt-4">
            <a class="underline text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-gray-100 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800" href="{{ route('login') }}">
                {{ __('Already registered?') }}
            </a>

            <x-primary-button class="ms-4">
                {{ $isInvite ? __('Create teacher account') : __('Register') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
