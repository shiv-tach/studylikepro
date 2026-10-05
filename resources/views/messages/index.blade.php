@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $isTeacher = auth()->user()->isTeacher();
    $timezone = auth()->user()->studentProfile?->timezone
        ?? auth()->user()->teacherProfile?->timezone
        ?? config('studylikepro.default_display_timezone');
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

    <div class="mx-auto max-w-3xl space-y-4">
        @if ($totalUnread > 0)
            <div class="rounded-2xl border border-primary/30 bg-primary/5 p-4 text-sm text-primary">
                {{ trans_choice(':count unread message waiting for you.|:count unread messages waiting for you.', $totalUnread) }}
            </div>
        @endif

        @forelse ($conversations as $conversation)
            @php
                $unread = $unread[$conversation->id] ?? 0;
                $lesson = $conversation->booking;
                $title = $lesson?->topic?->name ?? $lesson?->subject?->name ?? __('Tutoring lesson');
                $latest = $conversation->latestMessage;
                $counterpart = $conversation->counterpartName(auth()->user());
            @endphp

            <a href="{{ route('messages.show', $conversation) }}"
               class="flex items-start gap-4 {{ $cardBase }} transition-colors hover:border-primary/40 {{ $cardTheme }}">
                <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-sm font-bold text-primary">
                    {{ mb_substr($counterpart, 0, 2) }}
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex items-center justify-between gap-3">
                        <p class="truncate text-sm font-bold text-slate-800 dark:text-slate-100">
                            {{ $counterpart }}
                            @if ($unread > 0)
                                <span class="ml-1 inline-flex min-w-5 items-center justify-center rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-bold text-white">{{ $unread }}</span>
                            @endif
                        </p>
                        <span class="shrink-0 text-xs text-slate-400">
                            {{ ($conversation->last_message_at ?? $conversation->created_at)->diffForHumans() }}
                        </span>
                    </div>

                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                        {{ $title }}
                        @if ($lesson)
                            · {{ $lesson->starts_at->copy()->setTimezone($timezone)->format('D d M Y, H:i') }}
                        @endif
                    </p>

                    <p class="mt-2 truncate text-sm {{ $unread > 0 ? 'font-semibold text-slate-700 dark:text-slate-200' : 'text-slate-500 dark:text-slate-400' }}">
                        @if ($latest?->isSystem())
                            <span class="italic">{{ $latest->body }}</span>
                        @elseif ($latest)
                            {{ $latest->wasSentBy(auth()->user()) ? __('You: ') : '' }}{{ $latest->body ?: __('Photo') }}
                        @else
                            <span class="italic">{{ __('No messages yet — say hello.') }}</span>
                        @endif
                    </p>
                </div>
            </a>
        @empty
            <div class="{{ $cardBase }} text-center" :class="{{ $cardTheme }}">
                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ __('No lesson chats yet') }}</p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('A chat opens automatically with every confirmed lesson, so you can swap notes before you meet.') }}
                </p>
                <a href="{{ $isTeacher ? route('teacher.schedule.index') : route('student.bookings.index') }}"
                   class="mt-5 inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary/90">
                    {{ $isTeacher ? __('Open my schedule') : __('See my lessons') }}
                </a>
            </div>
        @endforelse

        @if ($conversations->hasPages())
            <div>{{ $conversations->links() }}</div>
        @endif
    </div>
</x-app-layout>
