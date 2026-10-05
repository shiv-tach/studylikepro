@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $emailOn = auth()->user()->wantsEmailNotifications();
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

    <div class="mx-auto max-w-3xl space-y-4">
        @if (session('status') === 'notifications-read')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                {{ __('All caught up — everything is marked as read.') }}
            </div>
        @endif

        <div class="{{ $cardBase }} flex flex-wrap items-center justify-between gap-3" :class="{{ $cardTheme }}">
            <div>
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                    {{ $emailOn ? __('Emails are on') : __('Emails are off') }}
                </p>
                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                    {{ $emailOn
                        ? __('You get an email copy of the important updates. In-app notifications always arrive here.')
                        : __('You only get in-app notifications — flip the switch in Settings to add emails.') }}
                </p>
            </div>
            <a href="{{ route('settings.index') }}"
               class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                {{ $emailOn ? __('Turn emails off') : __('Turn emails on') }}
            </a>
        </div>

        @forelse ($notifications as $notification)
            @php
                $isUnread = $notification->read_at === null;
                $title = data_get($notification->data, 'title', __('Notification'));
                $body = data_get($notification->data, 'body');
                $url = data_get($notification->data, 'url');
            @endphp

            <form method="POST" action="{{ route('notifications.open', $notification->id) }}">
                @csrf
                <button type="submit"
                        class="flex w-full items-start gap-4 {{ $cardBase }} text-left transition-colors hover:border-primary/40 {{ $cardTheme }} {{ $isUnread ? 'border-l-4 border-l-primary' : '' }}">
                    <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full {{ $isUnread ? 'bg-primary' : 'bg-slate-300 dark:bg-slate-600' }}"></span>
                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-center justify-between gap-2">
                            <span class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ $title }}</span>
                            <span class="text-xs text-slate-400">{{ $notification->created_at->diffForHumans() }}</span>
                        </span>
                        @if ($body)
                            <span class="mt-1 block text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ $body }}</span>
                        @endif
                        @if ($isUnread)
                            <span class="mt-2 inline-flex items-center rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-primary">{{ __('New') }}</span>
                        @endif
                    </span>
                </button>
            </form>
        @empty
            <div class="{{ $cardBase }} text-center" :class="{{ $cardTheme }}">
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ __('No notifications yet') }}</p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Lesson reminders, payment receipts, refunds and verification updates all land here.') }}
                </p>
            </div>
        @endforelse

        @if ($notifications->hasPages())
            <div>{{ $notifications->links() }}</div>
        @endif
    </div>
</x-app-layout>
