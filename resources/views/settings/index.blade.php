<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">
            {{ __('Settings') }}
        </h2>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-8">

        @if (session('status') === 'notifications-updated')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                {{ __('Notification preferences saved.') }}
            </div>
        @endif

        {{-- Page Description --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
            <div class="flex items-start gap-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary/10">
                    <svg class="h-6 w-6 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100">Application Settings</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        Manage your application preferences, appearance, and account settings. Below are the suggested settings you can configure to personalize your experience.
                    </p>
                </div>
            </div>
        </div>

        {{-- Settings Cards Grid --}}
        <div class="grid gap-5 sm:grid-cols-2">

            {{-- Theme Settings Card --}}
            <a href="{{ route('settings.theme') }}"
               class="group flex flex-col rounded-2xl border border-slate-200 bg-white p-6 transition-all duration-200 hover:border-primary/40 hover:shadow-lg hover:shadow-primary/5 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-primary/40">
                <div class="mb-4 flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-violet-100 text-violet-600 transition-colors group-hover:bg-primary group-hover:text-white dark:bg-violet-900/30 dark:text-violet-400">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Theme & Appearance</h3>
                </div>
                <p class="text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                    Personalize your dashboard with pre-designed theme presets, accent colors, light/dark mode, and sidebar styles. Choose from Classic, Forest, Midnight, Sunset, Glass, and Ocean themes.
                </p>
                <div class="mt-4 flex items-center gap-1 text-sm font-medium text-primary">
                    <span>Configure theme</span>
                    <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </div>
            </a>

            {{-- Profile Settings Card --}}
            <a href="{{ route('profile.edit') }}"
               class="group flex flex-col rounded-2xl border border-slate-200 bg-white p-6 transition-all duration-200 hover:border-primary/40 hover:shadow-lg hover:shadow-primary/5 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-primary/40">
                <div class="mb-4 flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-100 text-emerald-600 transition-colors group-hover:bg-primary group-hover:text-white dark:bg-emerald-900/30 dark:text-emerald-400">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Profile Information</h3>
                </div>
                <p class="text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                    Update your account profile information including your name, email address, and profile photo. Keep your account details accurate and up to date.
                </p>
                <div class="mt-4 flex items-center gap-1 text-sm font-medium text-primary">
                    <span>Edit profile</span>
                    <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </div>
            </a>

            {{-- Notification Settings Card (Demo) --}}
            {{-- Notifications Card --}}
            <div class="group flex flex-col rounded-2xl border border-slate-200 bg-white p-6 transition-all duration-200 hover:border-primary/40 hover:shadow-lg hover:shadow-primary/5 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-primary/40">
                <div class="mb-4 flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Notifications</h3>
                        @php $unreadNotifications = auth()->user()->unreadNotifications()->count(); @endphp
                        @if ($unreadNotifications > 0)
                            <span class="inline-flex items-center rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-semibold uppercase text-primary">{{ $unreadNotifications }} unread</span>
                        @else
                            <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-slate-500 dark:bg-slate-800 dark:text-slate-400">Up to date</span>
                        @endif
                    </div>
                </div>
                <p class="text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                    Lesson reminders, payments, refunds and verification updates always land in your in-app <a href="{{ route('notifications.index') }}" class="font-semibold text-primary hover:underline">notification centre</a>. Choose whether we also email you about them.
                </p>
                <form method="POST" action="{{ route('settings.notifications.update') }}" class="mt-4 space-y-3">
                    @csrf
                    <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-800">
                        <input type="checkbox" name="email" value="1" @checked(auth()->user()->wantsEmailNotifications())
                               class="mt-0.5 h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary dark:border-slate-600 dark:bg-slate-800">
                        <span>
                            <span class="block text-sm font-semibold text-slate-700 dark:text-slate-200">Email me about my lessons and money</span>
                            <span class="block text-xs text-slate-500 dark:text-slate-400">Reminders, confirmations, refunds, payouts and verification results.</span>
                        </span>
                    </label>
                    <x-primary-button>{{ __('Save notification preferences') }}</x-primary-button>
                </form>
                <a href="{{ route('notifications.index') }}" class="mt-4 flex items-center gap-1 text-sm font-medium text-primary">
                    {{ __('View notifications') }}
                    <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </a>
            </div>

            {{-- Security Settings Card (Demo) --}}
            <div class="group flex flex-col rounded-2xl border border-slate-200 bg-white p-6 transition-all duration-200 hover:border-primary/40 hover:shadow-lg hover:shadow-primary/5 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-primary/40">
                <div class="mb-4 flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-rose-100 text-rose-600 dark:bg-rose-900/30 dark:text-rose-400">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Security</h3>
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-slate-500 dark:bg-slate-800 dark:text-slate-400">Coming Soon</span>
                    </div>
                </div>
                <p class="text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                    Update your password, enable two-factor authentication, and manage active sessions. Keep your account secure with the latest security features.
                </p>
                <div class="mt-4 flex items-center gap-1 text-sm font-medium text-slate-400 dark:text-slate-500">
                    <span>Available soon</span>
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>

            {{-- Data & Privacy Settings Card (Demo) --}}
            <div class="group flex flex-col rounded-2xl border border-slate-200 bg-white p-6 transition-all duration-200 hover:border-primary/40 hover:shadow-lg hover:shadow-primary/5 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-primary/40">
                <div class="mb-4 flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-cyan-100 text-cyan-600 dark:bg-cyan-900/30 dark:text-cyan-400">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Data & Privacy</h3>
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-slate-500 dark:bg-slate-800 dark:text-slate-400">Coming Soon</span>
                    </div>
                </div>
                <p class="text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                    Control your data sharing preferences, view privacy policies, and manage cookie settings. Your data privacy is important to us.
                </p>
                <div class="mt-4 flex items-center gap-1 text-sm font-medium text-slate-400 dark:text-slate-500">
                    <span>Available soon</span>
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>

            {{-- Store / Business Settings Card (Demo) --}}
            <div class="group flex flex-col rounded-2xl border border-slate-200 bg-white p-6 transition-all duration-200 hover:border-primary/40 hover:shadow-lg hover:shadow-primary/5 dark:border-slate-800 dark:bg-slate-900 dark:hover:border-primary/40">
                <div class="mb-4 flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Account & Billing</h3>
                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase text-slate-500 dark:bg-slate-800 dark:text-slate-400">Coming Soon</span>
                    </div>
                </div>
                <p class="text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                    Configure your billing details including currency preferences, invoices, and receipt customization for your Studylikepro account.
                </p>
                <div class="mt-4 flex items-center gap-1 text-sm font-medium text-slate-400 dark:text-slate-500">
                    <span>Available soon</span>
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>

        </div>

        {{-- Quick Tips Section --}}
        <div class="rounded-2xl border border-slate-200 bg-gradient-to-br from-primary/5 to-transparent p-6 dark:border-slate-800 dark:from-primary/10 dark:to-transparent">
            <div class="flex items-start gap-3">
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/20">
                    <svg class="h-5 w-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z" />
                    </svg>
                </div>
                <div>
                    <h4 class="text-sm font-bold text-slate-800 dark:text-slate-200">Quick Tips</h4>
                    <ul class="mt-2 space-y-1.5 text-sm text-slate-500 dark:text-slate-400">
                        <li class="flex items-start gap-2">
                            <span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-primary"></span>
                            <span>Start by configuring your <strong class="text-slate-700 dark:text-slate-300">Theme & Appearance</strong> to personalize the look and feel of your dashboard.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-primary"></span>
                            <span>Update your <strong class="text-slate-700 dark:text-slate-300">Profile Information</strong> to keep your account details current.</span>
                        </li>
                        <li class="flex items-start gap-2">
                            <span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-primary"></span>
                            <span>Open <strong class="text-slate-700 dark:text-slate-300">Notifications</strong> in the sidebar any time to see lesson, payment and verification updates, and choose whether we email them too.</span>
                        </li>
                    </ul>
                </div>
            </div>
        </div>

    </div>
</x-app-layout>
