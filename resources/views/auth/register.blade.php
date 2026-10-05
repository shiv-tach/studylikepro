<x-guest-layout>
    @php
        $defaultRole = request()->query('role') === 'teacher' ? 'teacher' : 'student';
        $defaultRole = old('role', $defaultRole);
        if (! in_array($defaultRole, ['student', 'teacher'], true)) {
            $defaultRole = 'student';
        }
    @endphp

    <form method="POST" action="{{ route('register') }}" x-data="{ role: '{{ $defaultRole }}' }">
        @csrf

        <!-- Name -->
        <div>
            <x-input-label for="name" :value="__('Name')" />
            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" class="mt-2" />
        </div>

        <!-- Email Address -->
        <div class="mt-4">
            <x-input-label for="email" :value="__('Email')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <!-- Account Type -->
        <div class="mt-4">
            <x-input-label :value="__('I am joining as')" />
            <div class="mt-2 grid grid-cols-2 gap-3">
                <label class="flex cursor-pointer flex-col gap-1 rounded-2xl border p-3 transition-all"
                       :class="role === 'student' ? 'border-primary bg-primary/10 shadow-sm shadow-primary/10' : 'border-slate-200/80 hover:border-primary/40 dark:border-slate-700'">
                    <input type="radio" name="role" value="student" x-model="role" class="sr-only" />
                    <span class="flex items-center gap-2 text-sm font-semibold" :class="role === 'student' ? 'text-primary' : 'text-slate-700 dark:text-slate-300'">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                        </svg>
                        Student
                    </span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">Book lessons and get help with your questions</span>
                </label>

                <label class="flex cursor-pointer flex-col gap-1 rounded-2xl border p-3 transition-all"
                       :class="role === 'teacher' ? 'border-primary bg-primary/10 shadow-sm shadow-primary/10' : 'border-slate-200/80 hover:border-primary/40 dark:border-slate-700'">
                    <input type="radio" name="role" value="teacher" x-model="role" class="sr-only" />
                    <span class="flex items-center gap-2 text-sm font-semibold" :class="role === 'teacher' ? 'text-primary' : 'text-slate-700 dark:text-slate-300'">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4 2 9l10 5 10-5-10-5z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 11v3c0 1.4 2.7 2.6 6 2.6s6-1.2 6-2.6v-3" />
                        </svg>
                        Teacher
                    </span>
                    <span class="text-xs text-slate-500 dark:text-slate-400">Offer lessons and earn on your schedule</span>
                </label>
            </div>
            <x-input-error :messages="$errors->get('role')" class="mt-2" />
        </div>

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
                {{ __('Register') }}
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
