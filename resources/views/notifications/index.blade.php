@php
    // /notifications is rendered by resources/js/notifications. Blade keeps the
    // email preference, each notification flattened to what the row draws, and
    // the route map, then hands them over as a JSON island, so no title, time or
    // URL is hardcoded in Vue.
    $user = Auth::user();
    $emailOn = $user->wantsEmailNotifications();

    $notificationRows = $notifications->getCollection()->map(function ($notification) {
        $body = data_get($notification->data, 'body');

        return [
            'id' => $notification->id,
            'title' => (string) data_get($notification->data, 'title', __('Notification')),
            'body' => is_string($body) && $body !== '' ? $body : null,
            'time' => $notification->created_at->diffForHumans(),
            'unread' => $notification->read_at === null,
            // The row posts to this URL so opening a notification also marks it
            // read and follows its target, exactly like the Blade version did.
            'openUrl' => route('notifications.open', $notification->id),
        ];
    })->values()->all();

    $notificationsPayload = [
        'appName' => config('app.name', 'Studylikepro'),
        'csrfToken' => csrf_token(),
        'theme' => ['preset' => $userTheme['preset'] ?? 'classic'],
        'unreadCount' => $unreadCount,
        'emailEnabled' => $emailOn,
        'notifications' => $notificationRows,
        'status' => [
            'read' => session('status') === 'notifications-read',
        ],
        'copy' => [
            'readBanner' => __('All caught up — everything is marked as read.'),
            'emailsOn' => __('Emails are on'),
            'emailsOff' => __('Emails are off'),
            'emailsOnHint' => __('You get an email copy of the important updates. In-app notifications always arrive here.'),
            'emailsOffHint' => __('You only get in-app notifications — flip the switch in Settings to add emails.'),
            'turnOff' => __('Turn emails off'),
            'turnOn' => __('Turn emails on'),
            'emptyTitle' => __('No notifications yet'),
            'emptyHint' => __('Lesson reminders, payment receipts, refunds and verification updates all land here.'),
            'newBadge' => __('New'),
        ],
        'urls' => [
            'settings' => route('settings.index'),
            'readAll' => route('notifications.read-all'),
        ],
    ];

    // JSON_UNESCAPED_SLASHES keeps the URLs readable; the HEX flags keep the
    // island safe inside a <script> tag.
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Notifications') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Lesson updates, payments, refunds and verification results — where to find them and whether we email you as well.') }}
                </p>
            </div>

            <div class="flex items-center gap-3">
                @if ($unreadCount > 0)
                    <form method="POST" action="{{ route('notifications.read-all') }}">
                        @csrf
                        <x-primary-button>{{ __('Mark all as read') }}</x-primary-button>
                    </form>
                @endif
                <a href="{{ route('settings.index') }}" class="text-sm font-semibold text-primary hover:underline">{{ __('Preferences') }}</a>
            </div>
        </div>
    </x-slot>

    <script type="application/json" id="notifications-config">@json($notificationsPayload, $jsonFlags)</script>

    <div id="notifications-app"></div>

    {{-- The pager stays Blade-rendered: it is chrome around the list, not part of
         the rows the Vue app draws. --}}
    @if ($notifications->hasPages())
        <div class="mx-auto mt-4 max-w-3xl">{{ $notifications->links() }}</div>
    @endif

    {{-- Fallback for a browser without JavaScript: the same rows as real POST
         forms, each one still marking itself read and following its link. --}}
    <noscript>
        <div class="mx-auto max-w-3xl space-y-4">
            @if (session('status') === 'notifications-read')
                <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                    {{ __('All caught up — everything is marked as read.') }}
                </div>
            @endif

            <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                    {{ $emailOn ? __('Emails are on') : __('Emails are off') }}
                </p>
                <a href="{{ route('settings.index') }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 dark:border-slate-700 dark:text-slate-300">
                    {{ $emailOn ? __('Turn emails off') : __('Turn emails on') }}
                </a>
            </div>

            @forelse ($notifications as $notification)
                <form method="POST" action="{{ route('notifications.open', $notification->id) }}">
                    @csrf
                    <button type="submit" class="flex w-full items-start gap-4 rounded-2xl border border-slate-200 bg-white p-6 text-left dark:border-slate-800 dark:bg-slate-900">
                        <span class="min-w-0 flex-1">
                            <span class="block text-sm font-bold text-slate-800 dark:text-slate-100">{{ data_get($notification->data, 'title', __('Notification')) }}</span>
                            <span class="mt-1 block text-xs text-slate-400">{{ $notification->created_at->diffForHumans() }}</span>
                        </span>
                    </button>
                </form>
            @empty
                <div class="rounded-2xl border border-slate-200 bg-white p-6 text-center dark:border-slate-800 dark:bg-slate-900">
                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ __('No notifications yet') }}</p>
                </div>
            @endforelse
        </div>
    </noscript>

    @vite('resources/js/notifications/main.js')
</x-app-layout>
