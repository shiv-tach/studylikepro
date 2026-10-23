@php
    use App\Models\Conversation;

    // The lesson-chat inbox is rendered by resources/js/messages. Blade keeps
    // the perspective (student or teacher), the unread counts and the relative
    // timestamps, then hands the pre-formatted threads over as a JSON island, so
    // no label, figure or URL is ever hardcoded in the Vue app.
    $user = auth()->user();
    $isTeacher = $user->isTeacher();
    $timezone = $user->studentProfile?->timezone
        ?? $user->teacherProfile?->timezone
        ?? config('studylikepro.default_display_timezone');
    $themePreset = $user->theme['preset'];

    $threads = $conversations->getCollection()->map(function (Conversation $conversation) use ($user, $unread, $timezone) {
        $booking = $conversation->booking;
        $latest = $conversation->latestMessage;
        $counterpart = $conversation->counterpartName($user);
        $count = (int) ($unread[$conversation->id] ?? 0);

        // The one-line preview: the system notice and the empty nudge are
        // italic, a photo with no caption reads as "Photo", and an own message
        // is prefixed so the sender is obvious at a glance.
        $preview = __('No messages yet — say hello.');
        $previewSystem = false;
        $previewEmpty = true;

        if ($latest !== null) {
            $previewEmpty = false;
            $previewSystem = $latest->isSystem();
            $preview = ($latest->wasSentBy($user) ? __('You: ') : '').($latest->body ?: __('Photo'));
        }

        return [
            'id' => $conversation->id,
            'url' => route('messages.show', $conversation),
            'initials' => mb_strtoupper(mb_substr($counterpart, 0, 2)),
            'counterpart' => $counterpart,
            'unread' => $count,
            'unreadLabel' => $count > 0
                ? trans_choice(':count unread message|:count unread messages', $count)
                : null,
            'at' => ($conversation->last_message_at ?? $conversation->created_at)->diffForHumans(),
            'title' => $booking?->lesson?->name ?? $booking?->subject?->name ?? __('Tutoring lesson'),
            'schedule' => $booking?->starts_at
                ? display_datetime($booking->starts_at->copy()->setTimezone($timezone), 'D d M Y')
                : null,
            'preview' => $preview,
            'previewSystem' => $previewSystem,
            'previewEmpty' => $previewEmpty,
        ];
    })->values()->all();

    $messagesPayload = [
        'appName' => config('app.name', 'Studylikepro'),
        'theme' => ['preset' => $themePreset],
        'totalUnread' => $totalUnread,
        'threads' => $threads,
        'copy' => [
            'threadsLabel' => __('Lesson chats'),
            'unreadSummary' => $totalUnread > 0
                ? trans_choice(':count unread message waiting for you.|:count unread messages waiting for you.', $totalUnread)
                : null,
            'emptyTitle' => __('No lesson chats yet'),
            'emptyBody' => __('A chat opens automatically with every confirmed lesson, so you can swap notes before you meet.'),
            'emptyCta' => $isTeacher ? __('Open my lessons') : __('See my lessons'),
        ],
        'urls' => [
            'empty' => $isTeacher ? route('teacher.schedule.index') : route('student.bookings.index'),
        ],
    ];

    // JSON_UNESCAPED_SLASHES keeps the URLs readable; the HEX flags keep the
    // island safe inside a <script> tag.
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Messages') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ $isTeacher ? __('One thread per lesson, with the students you teach.') : __('One thread per lesson, with your teachers.') }}
            </p>
        </div>
    </x-slot>

    <div class="w-full">
        <script type="application/json" id="messages-config">@json($messagesPayload, $jsonFlags)</script>

        <div id="messages-app"></div>

        {{-- Fallback for a browser without JavaScript: the page is a thin shell,
             so list the same threads the Vue app would have rendered. --}}
        <noscript>
            <div class="mx-auto max-w-3xl space-y-4">
                @if ($totalUnread > 0)
                    <div class="rounded-2xl border border-primary/30 bg-primary/5 p-4 text-sm text-primary">
                        {{ $messagesPayload['copy']['unreadSummary'] }}
                    </div>
                @endif

                @forelse ($threads as $thread)
                    <a href="{{ $thread['url'] }}"
                       class="flex items-start gap-4 rounded-2xl border border-slate-200/80 bg-white p-6 transition-colors hover:border-primary/40 dark:border-slate-800/80 dark:bg-slate-900">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-sm font-bold text-primary">
                            {{ $thread['initials'] }}
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center justify-between gap-3">
                                <p class="truncate text-sm font-bold text-slate-800 dark:text-slate-100">
                                    {{ $thread['counterpart'] }}
                                    @if ($thread['unread'] > 0)
                                        <span class="ml-1 inline-flex min-w-5 items-center justify-center rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-bold text-white">{{ $thread['unread'] }}</span>
                                    @endif
                                </p>
                                <span class="shrink-0 text-xs text-slate-400">{{ $thread['at'] }}</span>
                            </div>

                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                {{ $thread['title'] }}
                                @if ($thread['schedule'])
                                    · {{ $thread['schedule'] }}
                                @endif
                            </p>

                            <p class="mt-2 truncate text-sm {{ $thread['unread'] > 0 ? 'font-semibold text-slate-700 dark:text-slate-200' : 'text-slate-500 dark:text-slate-400' }}">
                                @if ($thread['previewSystem'] || $thread['previewEmpty'])
                                    <span class="italic">{{ $thread['preview'] }}</span>
                                @else
                                    {{ $thread['preview'] }}
                                @endif
                            </p>
                        </div>
                    </a>
                @empty
                    <div class="rounded-2xl border border-slate-200/80 bg-white p-6 text-center dark:border-slate-800/80 dark:bg-slate-900">
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $messagesPayload['copy']['emptyTitle'] }}</p>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $messagesPayload['copy']['emptyBody'] }}</p>
                        <a href="{{ $messagesPayload['urls']['empty'] }}"
                           class="mt-5 inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary/90">
                            {{ $messagesPayload['copy']['emptyCta'] }}
                        </a>
                    </div>
                @endforelse
            </div>
        </noscript>

        {{-- Laravel keeps pagination server-rendered: the links are URLs and
             markup, which is exactly what Blade still owns. --}}
        @if ($conversations->hasPages())
            <div class="mx-auto mt-4 max-w-3xl">{{ $conversations->links() }}</div>
        @endif
    </div>

    @vite('resources/js/messages/main.js')
</x-app-layout>
