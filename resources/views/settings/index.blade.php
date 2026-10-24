@php
    // /settings is rendered by resources/js/settings-home. Blade decides which
    // cards exist, the unread badge, whether emails are on and every route, then
    // hands them over as a JSON island, so no label or URL is hardcoded in Vue.
    $user = Auth::user();
    $themePreset = $userTheme['preset'] ?? 'classic';
    $unreadNotifications = $user->unreadNotifications()->count();
    $emailOn = $user->wantsEmailNotifications();

    // Each card is one entry the Vue grid switches on. `link` cards navigate,
    // `notifications` carries the email toggle, `soon` cards are placeholders.
    $cards = [
        [
            'key' => 'theme',
            'kind' => 'link',
            'title' => __('Theme & Appearance'),
            'description' => __('Personalize your dashboard with pre-designed theme presets, accent colors, light/dark mode, and sidebar styles. Choose from Classic, Forest, Midnight, Sunset, Glass, and Ocean themes.'),
            'icon' => 'palette',
            'tone' => 'violet',
            'cta' => __('Configure theme'),
            'href' => route('settings.theme'),
        ],
        [
            'key' => 'profile',
            'kind' => 'link',
            'title' => __('Profile Information'),
            'description' => __('Update your account profile information including your name, email address, and profile photo. Keep your account details accurate and up to date.'),
            'icon' => 'user',
            'tone' => 'emerald',
            'cta' => __('Edit profile'),
            'href' => route('profile.edit'),
        ],
        [
            'key' => 'notifications',
            'kind' => 'notifications',
            'title' => __('Notifications'),
            'icon' => 'bell',
            'tone' => 'amber',
        ],
        [
            'key' => 'security',
            'kind' => 'soon',
            'title' => __('Security'),
            'description' => __('Update your password, enable two-factor authentication, and manage active sessions. Keep your account secure with the latest security features.'),
            'icon' => 'lock',
            'tone' => 'rose',
            'badge' => __('Coming soon'),
            'cta' => __('Available soon'),
        ],
        [
            'key' => 'privacy',
            'kind' => 'soon',
            'title' => __('Data & Privacy'),
            'description' => __('Control your data sharing preferences, view privacy policies, and manage cookie settings. Your data privacy is important to us.'),
            'icon' => 'shield',
            'tone' => 'cyan',
            'badge' => __('Coming soon'),
            'cta' => __('Available soon'),
        ],
        [
            'key' => 'billing',
            'kind' => 'soon',
            'title' => __('Account & Billing'),
            'description' => __('Configure your billing details including currency preferences, invoices, and receipt customization for your Studylikepro account.'),
            'icon' => 'credit-card',
            'tone' => 'blue',
            'badge' => __('Coming soon'),
            'cta' => __('Available soon'),
        ],
    ];

    $settingsPayload = [
        'appName' => config('app.name', 'Studylikepro'),
        'csrfToken' => csrf_token(),
        'theme' => ['preset' => $themePreset],
        'user' => [
            'name' => $user->displayName(),
            'fullName' => $user->name,
            'email' => $user->email,
            'initials' => Str::upper(Str::substr($user->name, 0, 2)),
            'avatarUrl' => $user->avatarUrl(),
        ],
        'notifications' => [
            'unreadCount' => $unreadNotifications,
            'emailEnabled' => $emailOn,
        ],
        'cards' => $cards,
        // Each tip is one sentence split around its emphasised phrase, so the
        // translation never has to be reassembled in JavaScript.
        'tips' => [
            [
                'prefix' => __('Start by configuring your '),
                'strong' => __('Theme & Appearance'),
                'suffix' => __(' to personalize the look and feel of your dashboard.'),
            ],
            [
                'prefix' => __('Update your '),
                'strong' => __('Profile Information'),
                'suffix' => __(' to keep your account details current.'),
            ],
            [
                'prefix' => __('Open '),
                'strong' => __('Notifications'),
                'suffix' => __(' in the sidebar any time to see lesson, payment and verification updates, and choose whether we email them too.'),
            ],
        ],
        'status' => [
            'notificationsUpdated' => session('status') === 'notifications-updated',
        ],
        'copy' => [
            'notificationsUpdated' => __('Notification preferences saved.'),
            'heroTitle' => __('Application Settings'),
            'heroDescription' => __('Manage your application preferences, appearance, and account settings. Below are the suggested settings you can configure to personalize your experience.'),
            'tipsTitle' => __('Quick Tips'),
            'unreadBadge' => __(':count unread'),
            'upToDateBadge' => __('Up to date'),
            'bodyPrefix' => __('Lesson reminders, payments, refunds and verification updates always land in your in-app '),
            'bodyLink' => __('notification centre'),
            'bodySuffix' => __('. Choose whether we also email you about them.'),
            'emailLabel' => __('Email me about my lessons and money'),
            'emailHint' => __('Reminders, confirmations, refunds, payouts and verification results.'),
            'save' => __('Save notification preferences'),
            'saving' => __('Saving...'),
            'viewNotifications' => __('View notifications'),
        ],
        'urls' => [
            'theme' => route('settings.theme'),
            'profile' => route('profile.edit'),
            'notifications' => route('notifications.index'),
            'notificationsUpdate' => route('settings.notifications.update'),
        ],
    ];

    // JSON_UNESCAPED_SLASHES keeps the URLs readable; the HEX flags keep the
    // island safe inside a <script> tag.
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">
            {{ __('Settings') }}
        </h2>
    </x-slot>

    <script type="application/json" id="settings-config">@json($settingsPayload, $jsonFlags)</script>

    <div id="settings-app"></div>

    {{-- Fallback for a browser without JavaScript: the same cards as plain links
         and the same email preference as a real POST form. --}}
    <noscript>
        <div class="mx-auto max-w-5xl space-y-8">
            @if (session('status') === 'notifications-updated')
                <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                    {{ __('Notification preferences saved.') }}
                </div>
            @endif

            <div class="grid gap-5 sm:grid-cols-2">
                @foreach ($cards as $card)
                    @if ($card['kind'] === 'link')
                        <a href="{{ $card['href'] }}" class="flex flex-col rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                            <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ $card['title'] }}</h3>
                            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $card['description'] }}</p>
                            <span class="mt-4 text-sm font-medium text-primary">{{ $card['cta'] }}</span>
                        </a>
                    @elseif ($card['kind'] === 'notifications')
                        <div class="flex flex-col rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                            <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ $card['title'] }}</h3>
                            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                                {{ __('Lesson reminders, payments, refunds and verification updates always land in your in-app ') }}<a href="{{ route('notifications.index') }}" class="font-semibold text-primary hover:underline">{{ __('notification centre') }}</a>{{ __('. Choose whether we also email you about them.') }}
                            </p>
                            <form method="POST" action="{{ route('settings.notifications.update') }}" class="mt-4 space-y-3">
                                @csrf
                                <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-slate-200 p-3 dark:border-slate-800">
                                    <input type="checkbox" name="email" value="1" @checked($emailOn)
                                           class="mt-0.5 h-4 w-4 rounded border-slate-300 text-primary focus:ring-primary dark:border-slate-600 dark:bg-slate-800">
                                    <span class="text-sm text-slate-700 dark:text-slate-200">{{ __('Email me about my lessons and money') }}</span>
                                </label>
                                <x-primary-button>{{ __('Save notification preferences') }}</x-primary-button>
                            </form>
                        </div>
                    @else
                        <div class="flex flex-col rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                            <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ $card['title'] }} · {{ $card['badge'] }}</h3>
                            <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">{{ $card['description'] }}</p>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    </noscript>

    @vite('resources/js/settings-home/main.js')
</x-app-layout>
