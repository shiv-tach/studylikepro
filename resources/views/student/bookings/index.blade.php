@php
    use App\Enums\BookingStatus;

    // My Lessons is rendered by resources/js/student-lessons. Blade owns the
    // copy, the tab and filter URLs and the pre-formatted lesson cards, then
    // hands them over as a JSON island, so no URL, figure or translated string
    // is ever hardcoded in the Vue app.
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);
    $timezone = auth()->user()->studentProfile?->timezone ?? config('studylikepro.default_display_timezone');
    $themePreset = auth()->user()->theme['preset'];

    // Each tab keeps the filters the student already applied, so switching
    // between Upcoming and Past never silently drops a narrowed history.
    $tabs = collect(['upcoming', 'past'])->map(fn (string $key) => [
        'key' => $key,
        'label' => ($key === 'upcoming' ? __('Upcoming') : __('Past')).' ('.$counts[$key].')',
        'url' => route('student.bookings.index', array_merge(request()->query(), ['tab' => $key])),
    ])->values()->all();

    $hasActiveFilters = collect($filters)->except('tab')->filter()->isNotEmpty();

    // Filtering earns its panel only once the tab holds more than a handful of
    // lessons. The count is the tab's whole history rather than the filtered
    // total, so narrowing the list never hides the panel that did the narrowing.
    $showFilters = $counts[$tab] > 5;

    $cards = collect($bookings->items())->map(function ($booking) use ($money, $timezone) {
        $title = collect([$booking->subject?->name, $booking->lesson?->name])->filter()->implode(' · ')
            ?: ($booking->tutoringRequest?->description
                ? Str::limit($booking->tutoringRequest->description, 60)
                : __('Tutoring lesson'));

        return [
            'id' => $booking->id,
            'url' => route('student.bookings.show', $booking),
            'status' => $booking->status->value,
            'statusLabel' => $booking->status->label(),
            // A held slot is the only card racing a clock, so the countdown is
            // decided here rather than in the Vue app.
            'payByLabel' => $booking->isHold() && $booking->expires_at?->isFuture()
                ? __('Pay within :time', ['time' => $booking->expires_at->diffForHumans()])
                : null,
            'durationLabel' => $booking->durationMinutes().' '.__('min'),
            'joinNow' => $booking->canJoinNow(),
            'reviewPrompt' => $booking->status === BookingStatus::Completed && $booking->review === null,
            'title' => $title,
            'subtitle' => __('with :name', ['name' => $booking->teacherProfile->user->name]),
            'startsLabel' => $booking->starts_at->copy()->setTimezone($timezone)->format('D d M, H:i'),
            'endsLabel' => $booking->ends_at->copy()->setTimezone($timezone)->format('H:i'),
            'amountLabel' => __('Total'),
            'amount' => $money($booking->totalMinor()),
        ];
    })->values()->all();

    $lessonsPayload = [
        'appName' => config('app.name', 'Studylikepro'),
        'theme' => ['preset' => $themePreset],
        'flash' => ['rescheduled' => session('status') === 'booking-reschedule'],
        'tab' => $tab,
        'tabs' => $tabs,
        'filters' => [
            'status' => $filters['status'] ?? '',
            'subject' => isset($filters['subject']) ? (string) $filters['subject'] : '',
            'teacher' => isset($filters['teacher']) ? (string) $filters['teacher'] : '',
            'from' => $filters['from'] ?? '',
            'to' => $filters['to'] ?? '',
        ],
        'statusOptions' => collect($statuses)
            ->map(fn (BookingStatus $status) => ['value' => $status->value, 'label' => $status->label()])
            ->values()->all(),
        // The filter form offers the student's own history, never the whole
        // catalog: a subject or teacher they never booked cannot match anyway.
        'subjectOptions' => $subjects
            ->map(fn ($subject) => ['value' => (string) $subject->id, 'label' => $subject->name])
            ->values()->all(),
        'teacherOptions' => $teachers
            ->map(fn ($teacher) => ['value' => (string) $teacher->id, 'label' => $teacher->user->name])
            ->values()->all(),
        'hasActiveFilters' => $hasActiveFilters,
        'showFilters' => $showFilters,
        'matchLabel' => $hasActiveFilters
            ? trans_choice(':count lesson matches these filters|:count lessons match these filters', $bookings->total())
            : null,
        'lessons' => $cards,
        'copy' => [
            'rescheduled' => __('Previous lesson released. Pick a new time below.'),
            'tabsLabel' => __('Lesson views'),
            'filterHistory' => __('Filter your lesson history'),
            'status' => __('Status'),
            'anyStatus' => __('Any status'),
            'subject' => __('Subject'),
            'anySubject' => __('Any subject'),
            'teacher' => __('Teacher'),
            'anyTeacher' => __('Any teacher'),
            'from' => __('From'),
            'to' => __('To'),
            'filter' => __('Filter'),
            'reset' => __('Reset'),
            'joinNow' => __('Join now'),
            'reviewPrompt' => __('Review this lesson'),
            'emptyUpcomingTitle' => __('No lessons booked yet'),
            'emptyUpcomingBody' => __('Pick a verified teacher, choose one of their open slots, and your lesson appears here the moment you book it.'),
            'emptyPastTitle' => __('Nothing in your history yet'),
            'emptyPastBody' => __('Completed, cancelled and expired lessons will show up here.'),
            'browseTeachers' => __('Browse teachers'),
        ],
        'urls' => [
            'index' => route('student.bookings.index'),
            'reset' => route('student.bookings.index', ['tab' => $tab]),
            'teachers' => route('student.teachers.index'),
        ],
    ];

    // JSON_UNESCAPED_SLASHES keeps the URLs readable; the HEX flags keep the
    // island safe inside a <script> tag.
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('My lessons') }}</h2>
            <a href="{{ route('student.teachers.index') }}"
               class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary/90">
                {{ __('Find a teacher') }}
            </a>
        </div>
    </x-slot>

    <div class="w-full">
        <script type="application/json" id="student-lessons-config">@json($lessonsPayload, $jsonFlags)</script>

        <div id="student-lessons-app" class="space-y-6"></div>

        {{-- Fallback for a browser without JavaScript: the page is a thin shell,
             so list the same lessons the Vue app would have rendered. --}}
        <noscript>
            <div class="space-y-6">
                <div class="flex flex-wrap gap-2">
                    @foreach ($tabs as $tabLink)
                        <a href="{{ $tabLink['url'] }}"
                           class="rounded-xl px-4 py-2 text-sm font-semibold {{ $tab === $tabLink['key']
                                ? 'bg-primary text-white shadow-sm'
                                : 'border border-slate-200 text-slate-600 dark:border-slate-800 dark:text-slate-300' }}">
                            {{ $tabLink['label'] }}
                        </a>
                    @endforeach
                </div>

                @forelse ($bookings as $booking)
                    <div class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                        <p class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                            {{ $booking->status->label() }} · {{ $booking->durationMinutes() }} {{ __('min') }}
                        </p>
                        <h3 class="mt-2 text-sm font-bold text-slate-800 dark:text-slate-100">
                            <a class="hover:underline" href="{{ route('student.bookings.show', $booking) }}">
                                {{ collect([$booking->subject?->name, $booking->lesson?->name])->filter()->implode(' · ') ?: __('Tutoring lesson') }}
                            </a>
                        </h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            {{ __('with :name', ['name' => $booking->teacherProfile->user->name]) }}
                            · {{ $booking->starts_at->copy()->setTimezone($timezone)->format('D d M, H:i') }}–{{ $booking->ends_at->copy()->setTimezone($timezone)->format('H:i') }}
                        </p>
                    </div>
                @empty
                    <div class="rounded-2xl border border-slate-200/80 bg-white p-6 text-center dark:border-slate-800/80 dark:bg-slate-900">
                        <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">
                            {{ $tab === 'upcoming' ? __('No lessons booked yet') : __('Nothing in your history yet') }}
                        </h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            {{ $tab === 'upcoming'
                                ? __('Pick a verified teacher, choose one of their open slots, and your lesson appears here the moment you book it.')
                                : __('Completed, cancelled and expired lessons will show up here.') }}
                        </p>
                    </div>
                @endforelse

                <p class="text-sm">
                    <a class="font-semibold text-primary hover:underline" href="{{ route('student.teachers.index') }}">
                        {{ __('Browse teachers') }}
                    </a>
                </p>
            </div>
        </noscript>

        {{-- Laravel keeps pagination server-rendered: the links are URLs and
             markup, which is exactly what Blade still owns. --}}
        @if ($bookings->hasPages())
            <div class="mt-6">{{ $bookings->links() }}</div>
        @endif
    </div>

    @vite('resources/js/student-lessons/main.js')
</x-app-layout>
