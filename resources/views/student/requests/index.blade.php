@php
    use App\Enums\RequestStatus;

    // The request list is rendered by resources/js/student-requests. Blade owns
    // the copy, the pre-formatted card data and the route map, then hands them
    // over as a JSON island, so no URL or string is hardcoded in the Vue app.
    $themePreset = $userTheme['preset'] ?? 'classic';
    $timezone = auth()->user()->studentProfile?->timezone ?? config('studylikepro.default_display_timezone');

    // Requests fall into three buckets so the list filters without the Vue app
    // ever knowing the enum behind a status.
    $groupOf = fn (RequestStatus $status) => match ($status) {
        RequestStatus::Open => 'open',
        RequestStatus::Matched => 'matched',
        default => 'closed',
    };

    $cards = collect($requests->items())->map(fn ($tutoringRequest) => [
        'id' => $tutoringRequest->id,
        'url' => route('student.requests.show', $tutoringRequest),
        'status' => $tutoringRequest->status->value,
        'statusLabel' => $tutoringRequest->status->label(),
        'group' => $groupOf($tutoringRequest->status),
        'subject' => $tutoringRequest->subject === null ? null : [
            'icon' => $tutoringRequest->subject->icon ?? '📘',
            'name' => $tutoringRequest->subject->name,
        ],
        'lessonName' => $tutoringRequest->lesson?->name,
        // A card repeats the question text only when the heading is the lesson
        // that was matched, so the same sentence is never read twice.
        'showDescription' => $tutoringRequest->lesson !== null,
        // The classification chip only shows while the AI is still working on
        // the question, so that decision stays server-side.
        'classification' => $tutoringRequest->classification_status->isSettled() ? null : [
            'status' => $tutoringRequest->classification_status->value,
            'label' => $tutoringRequest->classification_status->label(),
        ],
        'proposals' => $tutoringRequest->responses_count > 0 ? [
            'count' => $tutoringRequest->responses_count,
            'label' => trans_choice(
                ':count teacher proposal|:count teacher proposals',
                $tutoringRequest->responses_count,
                ['count' => $tutoringRequest->responses_count],
            ),
        ] : null,
        'title' => $tutoringRequest->lesson?->name ?? Str::limit($tutoringRequest->description, 60),
        'description' => $tutoringRequest->description,
        'createdLabel' => $tutoringRequest->created_at->format('d M Y'),
        'expiresLabel' => $tutoringRequest->isOpen() && $tutoringRequest->expires_at !== null
            ? __('Open until :date', ['date' => $tutoringRequest->expires_at->format('d M')])
            : null,
        'windows' => $tutoringRequest->windowLabels($timezone),
    ])->values()->all();

    // The tiles and filter tabs describe the student's whole history, not just
    // the ten requests this page shows, so they count from the grouped query
    // the controller ran alongside the paginator.
    $statusCounts = collect($statusCounts ?? []);
    $sumOf = fn (RequestStatus ...$statuses) => collect($statuses)
        ->sum(fn (RequestStatus $status) => (int) ($statusCounts[$status->value] ?? 0));
    $totalCount = $requests->total();
    $openCount = $sumOf(RequestStatus::Open);
    $matchedCount = $sumOf(RequestStatus::Matched);

    $requestsPayload = [
        'appName' => config('app.name', 'Studylikepro'),
        'theme' => ['preset' => $themePreset],
        'flash' => ['cancelled' => session('status') === 'request-cancelled'],
        'requests' => $cards,
        'stats' => [
            ['key' => 'open', 'icon' => 'search', 'tone' => 'primary', 'value' => $openCount, 'label' => __('Waiting for a teacher')],
            ['key' => 'matched', 'icon' => 'check-circle', 'tone' => 'emerald', 'value' => $matchedCount, 'label' => __('Teacher found')],
            ['key' => 'total', 'icon' => 'clipboard', 'tone' => 'slate', 'value' => $totalCount, 'label' => __('Questions asked')],
        ],
        'filters' => [
            ['key' => 'all', 'label' => __('All'), 'count' => $totalCount],
            ['key' => 'open', 'label' => RequestStatus::Open->label(), 'count' => $openCount],
            ['key' => 'matched', 'label' => RequestStatus::Matched->label(), 'count' => $matchedCount],
            ['key' => 'closed', 'label' => __('Closed'), 'count' => $sumOf(RequestStatus::Cancelled, RequestStatus::Expired, RequestStatus::Closed)],
        ],
        'copy' => [
            'cancelled' => __('Request cancelled. Any teacher proposals for it have been withdrawn.'),
            'overviewTitle' => __('Your questions'),
            'overviewIntro' => __('Every question you have asked, and where each one stands right now.'),
            'filtersLabel' => __('Show'),
            'emptyTitle' => __('No requests yet'),
            'emptyBody' => __('Describe what you are stuck on, add a photo of the problem if you have one, and we will match it to the right lesson and teacher.'),
            'emptyHint' => __('Your question goes to verified teachers who teach that lesson — their proposals land here.'),
            'emptyCta' => __('Ask your first question'),
            'findTeacher' => __('Find a teacher'),
            'filterEmpty' => __('Nothing in this list yet.'),
            'preferredTimes' => __('You can attend'),
            'viewRequest' => __('View request'),
            'reviewProposals' => __('Review proposals'),
        ],
        'urls' => [
            'create' => route('student.requests.create'),
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
            <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('My tutoring requests') }}</h2>
            <a href="{{ route('student.requests.create') }}"
               class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary/90">
                {{ __('Ask a question') }}
            </a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-4xl">
        <script type="application/json" id="student-requests-config">@json($requestsPayload, $jsonFlags)</script>

        <div id="student-requests-app" class="space-y-6"></div>

        {{-- Fallback for a browser without JavaScript: the page is a thin shell,
             so list the same requests the Vue app would have rendered. --}}
        <noscript>
            <div class="space-y-6">
                @forelse ($requests as $tutoringRequest)
                    <div class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                        <p class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                            {{ $tutoringRequest->status->label() }}
                        </p>
                        <h3 class="mt-2 text-sm font-bold text-slate-800 dark:text-slate-100">
                            <a class="hover:underline" href="{{ route('student.requests.show', $tutoringRequest) }}">
                                {{ $tutoringRequest->lesson?->name ?? Str::limit($tutoringRequest->description, 60) }}
                            </a>
                        </h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $tutoringRequest->description }}</p>
                    </div>
                @empty
                    <div class="rounded-2xl border border-slate-200/80 bg-white p-6 text-center dark:border-slate-800/80 dark:bg-slate-900">
                        <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ __('No requests yet') }}</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            {{ __('Describe what you are stuck on, add a photo of the problem if you have one, and we will match it to the right lesson and teacher.') }}
                        </p>
                    </div>
                @endforelse

                <p class="text-sm text-slate-500 dark:text-slate-400">
                    <a class="font-semibold text-primary hover:underline" href="{{ route('student.requests.create') }}">
                        {{ __('Ask a question') }}
                    </a>
                </p>
            </div>
        </noscript>

        {{-- Laravel keeps pagination server-rendered: the links are URLs and
             markup, which is exactly what Blade still owns. --}}
        @if ($requests->hasPages())
            <div class="mt-6">{{ $requests->links() }}</div>
        @endif
    </div>

    @vite('resources/js/student-requests/main.js')
</x-app-layout>
