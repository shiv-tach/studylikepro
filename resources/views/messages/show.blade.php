@php
    use App\Models\Dispute;

    // One lesson thread is rendered by resources/js/messages-thread. Blade keeps
    // the perspective (student or teacher), the pre-formatted lesson line, the
    // first page of messages in the poller's own payload shape and the report
    // form's state, then hands them over as a JSON island, so no URL, timestamp
    // or translated string is ever hardcoded in the Vue app.
    $user = auth()->user();
    $bookingsRoute = $isTeacher ? 'teacher.bookings.show' : 'student.bookings.show';
    $lesson = $conversation->booking;
    $lessonTitle = $lesson?->lesson?->name ?? $lesson?->subject?->name ?? __('Tutoring lesson');
    $timezone = $user->studentProfile?->timezone
        ?? $user->teacherProfile?->timezone
        ?? config('studylikepro.default_display_timezone');

    $lessonSummary = $lesson
        ? [
            'title' => $lessonTitle,
            'schedule' => $lesson->starts_at->copy()->setTimezone($timezone)->format('D d M Y, H:i'),
            'url' => route($bookingsRoute, $lesson),
        ]
        : null;

    // One banner per redirect this page can be reached with, so it explains what
    // just happened without needing a URL of its own.
    $flash = match (session('status')) {
        'report-received' => [
            'tone' => 'emerald',
            'icon' => 'check-circle',
            'message' => __('Thanks — our support team has this conversation and will follow up by email or in the app.'),
        ],
        'report-already-open' => [
            'tone' => 'amber',
            'icon' => 'alert',
            'message' => __('You already have an open report on this lesson — support will get back to you before long.'),
        ],
        default => null,
    };

    $threadPayload = [
        'appName' => config('app.name', 'Studylikepro'),
        'csrfToken' => csrf_token(),
        'theme' => ['preset' => $user->theme['preset']],
        'counterpart' => $counterpart,
        'initials' => mb_strtoupper(mb_substr($counterpart, 0, 2)),
        'lesson' => $lessonSummary,
        'messages' => $initialPayload,
        'flash' => $flash,
        'report' => [
            'open' => $reportAlreadyOpen,
            // A rejected report bounces straight back here, so the dialog reopens
            // with what was typed and the validation message beside the field.
            'errors' => [
                'reason' => $errors->first('reason') ?: null,
                'details' => $errors->first('details') ?: null,
            ],
            'old' => [
                'reason' => old('reason'),
                'details' => old('details'),
            ],
            // The reasons live in a PHP const; only their labels cross over so the
            // Vue dialog never guesses what support accepts.
            'reasons' => collect(Dispute::REASONS)->map(fn ($label, $value) => [
                'value' => $value,
                'label' => $label,
            ])->values()->all(),
        ],
        'copy' => [
            'lessonFallback' => __('Tutoring lesson'),
            'openLesson' => __('open the lesson'),
            'reportCta' => __('Report a problem'),
            'reportOpen' => __('Report under review'),
            'emptyHint' => __('No messages yet — say hello.'),
            'composerPlaceholder' => __('Write a message…'),
            'attach' => __('Attach a photo'),
            'removeAttachment' => __('Remove the photo'),
            'send' => __('Send'),
            'sending' => __('Sending…'),
            'you' => __('You'),
            'someone' => __('Someone'),
            'sharedPhoto' => __('Shared photo'),
            'sendHint' => __('Enter to send, Shift + Enter for a new line.'),
            'immutable' => __('Messages cannot be edited or deleted. Keep personal contact details out of the chat.'),
            'writeFirst' => __('Write a message or attach a photo first.'),
            'failed' => __('That message did not go through — please try again.'),
            'offline' => __('That message did not go through — check your connection.'),
            'reportTitle' => __('Report a problem'),
            'reportIntro' => __('This opens a dispute our support team reviews. Money issues, no-shows and behaviour reports all start here.'),
            'reasonLabel' => __('What happened?'),
            'detailsLabel' => __('Anything we should know? (optional)'),
            'cancel' => __('Cancel'),
            'submitReport' => __('Send to support'),
            'footer' => __('Something wrong with this lesson? Use "Report a problem" and our support team steps in — disputes and refunds are decided by Studylikepro, not by the chat.'),
        ],
        // The route map stays in Blade so no endpoint is ever hardcoded in Vue.
        'urls' => [
            'index' => route('messages.index'),
            'poll' => route('messages.poll', $conversation),
            'store' => route('messages.store', $conversation),
            'report' => route('messages.report', $conversation),
        ],
    ];

    // JSON_UNESCAPED_SLASHES keeps the URLs readable; the HEX flags keep the
    // island safe inside a <script> tag.
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex min-w-0 items-center gap-3">
            <a href="{{ route('messages.index') }}" class="shrink-0 text-xs font-semibold text-slate-400 transition-colors hover:text-primary">&larr; {{ __('All messages') }}</a>
            <h2 class="truncate font-bold text-xl leading-tight text-slate-800 dark:text-slate-100">{{ $counterpart }}</h2>
        </div>
    </x-slot>

    <div class="w-full">
        <script type="application/json" id="messages-thread-config">@json($threadPayload, $jsonFlags)</script>

        <div id="messages-thread-app"></div>

        {{-- Fallback for a browser without JavaScript: the page is a thin shell, so
             print the thread the Vue app would have rendered read-only, and keep the
             escalation form as a plain server form. --}}
        <noscript>
            <div class="mx-auto max-w-3xl space-y-4">
                @if ($lessonSummary)
                    <p class="px-2 text-xs text-slate-500 dark:text-slate-400">
                        {{ $lessonSummary['title'] }} · {{ $lessonSummary['schedule'] }} ·
                        <a href="{{ $lessonSummary['url'] }}" class="font-semibold text-primary hover:underline">{{ __('open the lesson') }}</a>
                    </p>
                @endif

                <div class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                    <div class="flex flex-col gap-4">
                        @forelse ($messages as $message)
                            @include('messages.partials.bubble', ['message' => $message, 'mine' => $message->wasSentBy($user)])
                        @empty
                            <p class="py-8 text-center text-sm italic text-slate-400">{{ __('No messages yet — say hello.') }}</p>
                        @endforelse
                    </div>
                </div>

                <p class="px-2 text-xs text-slate-500 dark:text-slate-400">
                    {{ __('JavaScript is switched off, so the chat cannot post here. Turn it on to reply.') }}
                </p>

                @unless ($reportAlreadyOpen)
                    <form method="POST" action="{{ route('messages.report', $conversation) }}" class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                        @csrf
                        <h2 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ __('Report a problem') }}</h2>

                        <div class="mt-4 space-y-4">
                            <div>
                                <x-input-label for="noscript-report-reason" :value="__('What happened?')" />
                                <select id="noscript-report-reason" name="reason" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                    @foreach (Dispute::REASONS as $value => $label)
                                        <option value="{{ $value }}" @selected(old('reason') === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="noscript-report-details" :value="__('Anything we should know? (optional)')" />
                                <textarea id="noscript-report-details" name="details" rows="3" maxlength="1000"
                                          class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">{{ old('details') }}</textarea>
                                <x-input-error :messages="$errors->get('details')" class="mt-2" />
                            </div>
                        </div>

                        <x-danger-button class="mt-6">{{ __('Send to support') }}</x-danger-button>
                    </form>
                @endunless
            </div>
        </noscript>
    </div>

    @vite('resources/js/messages-thread/main.js')
</x-app-layout>
