@php
    // /teacher/services is rendered by resources/js/teacher-services. Blade keeps
    // ownership of the service types, their price hints, every scope option, the
    // validated limits, the flash message, the old input and every route, then
    // hands them over as one JSON island. The <noscript> block below repeats the
    // same forms without JavaScript.
    $themePreset = $userTheme['preset'] ?? 'classic';
    $currencySymbol = platform_settings()->currencySymbol();
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);

    $typeIcons = [
        \App\Enums\ServiceType::Lesson->value => 'graduation',
        \App\Enums\ServiceType::Summary->value => 'clipboard',
        \App\Enums\ServiceType::PaperMarking->value => 'check-circle',
        \App\Enums\ServiceType::PaperDiscussion->value => 'chat',
        \App\Enums\ServiceType::Qna->value => 'video',
    ];

    $typeHints = [
        \App\Enums\ServiceType::Lesson->value => __('A fixed pool of lesson time students book in sessions. The price covers the whole plan.'),
        \App\Enums\ServiceType::Summary->value => __('A written summary of one lesson, delivered asynchronously.'),
        \App\Enums\ServiceType::PaperMarking->value => __('Marking per paper, with a turnaround promise.'),
        \App\Enums\ServiceType::PaperDiscussion->value => __('A live one-on-one session discussing a marked paper.'),
        \App\Enums\ServiceType::Qna->value => __('A live one-on-one session answering questions on a subject or one lesson.'),
    ];

    $priceSuffixes = [
        \App\Enums\ServiceType::Lesson->value => __('per plan'),
        \App\Enums\ServiceType::PaperMarking->value => __('per paper'),
    ];

    $priceHints = [
        \App\Enums\ServiceType::Lesson->value => __('Flat price for the whole plan.'),
        \App\Enums\ServiceType::PaperMarking->value => __('Flat price per paper.'),
    ];

    $durationLabel = function (int $minutes): string {
        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return match (true) {
            $hours > 0 && $rest > 0 => $hours.'h '.$rest.'m',
            $hours > 0 => $hours.'h',
            default => $rest.'m',
        };
    };

    $offeringsPayload = $offerings->map(function (\App\Models\ServiceOffering $offering) use (
        $durationLabel,
        $money,
        $priceSuffixes,
        $typeIcons
    ) {
        $type = $offering->service_type;

        $detailChips = [];

        if ($type === \App\Enums\ServiceType::Lesson && $offering->allocation_minutes) {
            $detailChips[] = $durationLabel((int) $offering->allocation_minutes).' '.__('total');
        }

        if ($type->isLiveSession() && $offering->default_session_minutes) {
            $detailChips[] = $offering->default_session_minutes.' '.__('min session');
        }

        if ($type === \App\Enums\ServiceType::PaperMarking) {
            $detailChips[] = $offering->max_pages
                ? $offering->max_pages.' '.__('pages')
                : __('Any pages');
            $detailChips[] = ((int) ($offering->turnaround_hours ?? config('studylikepro.services.marking_default_turnaround_hours'))).'h '.__('turnaround');
        }

        return [
            'id' => (int) $offering->id,
            'type' => $type->value,
            'typeLabel' => $type->label(),
            'typePluralLabel' => $type->pluralLabel(),
            'typeIcon' => $typeIcons[$type->value] ?? 'tag',
            'title' => $offering->title,
            'description' => $offering->description,
            'priceMinor' => (int) $offering->price_minor,
            'priceLabel' => $money((int) $offering->price_minor),
            'priceSuffix' => $priceSuffixes[$type->value] ?? __('per session'),
            'isActive' => (bool) $offering->is_active,
            'isLiveSession' => $type->isLiveSession(),
            'gradeLabel' => $offering->grade?->label,
            'subjectName' => $offering->subject?->name,
            'subjectIcon' => $offering->subject?->icon ?: '📘',
            'lessonName' => $offering->lesson?->name,
            'detailChips' => $detailChips,
            'purchasesCount' => (int) $offering->purchases_count,
            'canDelete' => (int) $offering->purchases_count === 0,
            'values' => [
                'title' => $offering->title,
                'description' => $offering->description,
                'price' => (string) (int) round($offering->price_minor / 100),
                'gradeId' => $offering->grade_id === null ? null : (int) $offering->grade_id,
                'subjectId' => $offering->subject_id === null ? null : (int) $offering->subject_id,
                'lessonId' => $offering->lesson_id === null ? null : (int) $offering->lesson_id,
                'allocationMinutes' => $offering->allocation_minutes === null ? null : (int) $offering->allocation_minutes,
                'defaultSessionMinutes' => $offering->default_session_minutes === null ? null : (int) $offering->default_session_minutes,
                'maxSessions' => $offering->max_sessions === null ? null : (int) $offering->max_sessions,
                'maxPages' => $offering->max_pages === null ? null : (int) $offering->max_pages,
                'turnaroundHours' => $offering->turnaround_hours === null ? null : (int) $offering->turnaround_hours,
            ],
            'updateUrl' => route('teacher.services.update', $offering),
            'deleteUrl' => route('teacher.services.destroy', $offering),
            'toggleUrl' => route('teacher.services.toggle', $offering),
        ];
    })->values();

    // A rejected save keeps the form open: a hidden `editing_offering` field
    // says which service failed, otherwise the posted `service_type` reopens the
    // create composer on the same tab.
    $editingOfferingId = (int) old('editing_offering', 0);
    $postedType = (string) old('service_type', '');
    $hasOldInput = session()->hasOldInput() && ($postedType !== '' || old('title') !== null);
    $knownTypes = array_map(fn (\App\Enums\ServiceType $type) => $type->value, $types);

    $openComposer = null;

    if ($editingOfferingId > 0) {
        $openComposer = ['mode' => 'edit', 'offeringId' => $editingOfferingId];
    } elseif ($hasOldInput && in_array($postedType, $knownTypes, true)) {
        $openComposer = ['mode' => 'create', 'type' => $postedType];
    }

    $blankToNull = fn ($value) => ($value === null || $value === '') ? null : (int) $value;

    $formValues = $hasOldInput ? [
        'title' => (string) old('title', ''),
        'description' => (string) old('description', ''),
        'price' => old('price') === null ? '' : (string) old('price'),
        'gradeId' => $blankToNull(old('grade_id')),
        'subjectId' => $blankToNull(old('subject_id')),
        'lessonId' => $blankToNull(old('lesson_id')),
        'allocationMinutes' => $blankToNull(old('allocation_minutes')),
        'defaultSessionMinutes' => $blankToNull(old('default_session_minutes')),
        'maxSessions' => $blankToNull(old('max_sessions')),
        'maxPages' => $blankToNull(old('max_pages')),
        'turnaroundHours' => $blankToNull(old('turnaround_hours')),
    ] : null;

    $errorKeys = [
        'service_type',
        'title',
        'description',
        'price',
        'grade_id',
        'subject_id',
        'lesson_id',
        'allocation_minutes',
        'default_session_minutes',
        'max_sessions',
        'max_pages',
        'turnaround_hours',
    ];

    $fieldErrors = [];

    foreach ($errorKeys as $key) {
        $messages = array_values($errors->get($key));

        if ($messages !== []) {
            $fieldErrors[$key] = $messages;
        }
    }

    $activeCount = $offeringsPayload->where('isActive', true)->count();
    $pausedCount = $offeringsPayload->count() - $activeCount;
    $soldCount = $offeringsPayload->sum('purchasesCount');

    $notices = [];

    if (session('status') === 'offering-created') {
        $notices[] = [
            'tone' => 'emerald',
            'title' => __('Service added'),
            'message' => __('Students can now find it on your profile.'),
        ];
    } elseif (session('status') === 'offering-updated') {
        $notices[] = [
            'tone' => 'emerald',
            'title' => __('Service updated'),
            'message' => __('Your changes are live on your profile.'),
        ];
    } elseif (session('status') === 'offering-deleted') {
        $notices[] = [
            'tone' => 'slate',
            'title' => __('Service removed'),
            'message' => __('It is no longer in your catalog.'),
        ];
    } elseif (session('status') === 'offering-toggled') {
        $notices[] = [
            'tone' => 'slate',
            'title' => __('Availability updated'),
            'message' => __('Students see the new status right away.'),
        ];
    } elseif (session('status') === 'offering-has-purchases') {
        $notices[] = [
            'tone' => 'amber',
            'title' => __('This service has purchases'),
            'message' => __("It can't be deleted. Pause it instead to stop new sales."),
        ];
    }

    $typesPayload = collect($types)->map(function (\App\Enums\ServiceType $type) use (
        $offeringsPayload,
        $priceHints,
        $priceSuffixes,
        $typeHints,
        $typeIcons
    ) {
        $rows = $offeringsPayload->where('type', $type->value);

        return [
            'value' => $type->value,
            'label' => $type->label(),
            'pluralLabel' => $type->pluralLabel(),
            'icon' => $typeIcons[$type->value] ?? 'tag',
            'emoji' => $type->icon(),
            'hint' => $typeHints[$type->value] ?? '',
            'priceSuffix' => $priceSuffixes[$type->value] ?? __('per session'),
            'priceHint' => $priceHints[$type->value] ?? __('Flat price per session.'),
            'requiresLesson' => $type->requiresLesson(),
            'allowsLessonOrSubject' => $type->allowsLessonOrSubject(),
            'requiresSubject' => $type->requiresSubject(),
            'isLiveSession' => $type->isLiveSession(),
            'count' => $rows->count(),
            'activeCount' => $rows->where('isActive', true)->count(),
        ];
    })->values();

    $copy = [
        'hero' => [
            'eyebrow' => __('Service catalog'),
            'title' => __('Services & pricing'),
            'intro' => __('Create the ready-made services students can buy from you. Each one is priced per subject or lesson, and you can pause it whenever you need.'),
            'sideNote' => __('Pause a service any time to stop new sales — existing purchases keep working.'),
            'cta' => __('New service'),
        ],
        'stats' => [
            'label' => __('Your services at a glance'),
            'total' => trans_choice(':count service|:count services', $offeringsPayload->count()),
            'active' => trans_choice(':count active|:count active', $activeCount),
            'paused' => trans_choice(':count paused|:count paused', $pausedCount),
            'sold' => trans_choice(':count sold|:count sold', (int) $soldCount),
        ],
        'tabs' => [
            'label' => __('Service types'),
            'all' => __('All services'),
        ],
        'toolbar' => [
            'searchLabel' => __('Search services'),
            'searchPlaceholder' => __('Search by title, subject or lesson'),
            'clearSearch' => __('Clear search'),
            'statusLabel' => __('Availability'),
            'statusAll' => __('All'),
            'statusActive' => __('Active'),
            'statusPaused' => __('Paused'),
            'resultsOne' => __(':count service'),
            'results' => __(':count services'),
        ],
        'card' => [
            'active' => __('Active'),
            'paused' => __('Paused'),
            'soldOne' => __(':count sold'),
            'sold' => __(':count sold'),
            'edit' => __('Edit'),
            'pause' => __('Pause'),
            'resume' => __('Resume'),
            'delete' => __('Delete'),
            'deleteBlocked' => __('This service has purchases, so it can only be paused.'),
            'noDescription' => __('No description yet.'),
        ],
        'list' => [
            'noSubjectsTitle' => __('Add subjects first'),
            'noSubjectsBody' => __('Every service is priced per subject or lesson, so add your subjects and lessons before creating one.'),
            'noSubjectsCta' => __('Set up subjects & lessons'),
            'emptyAllTitle' => __('No services yet'),
            'emptyAllBody' => __('Create your first service so students can buy a lesson plan, a summary, paper marking or a live session.'),
            'emptyTypeTitle' => __('No :type yet'),
            'emptyTypeBody' => __('Create your first :type and set the scope and price in a couple of steps.'),
            'emptyFilteredTitle' => __('Nothing matches those filters'),
            'emptyFilteredBody' => __('Try another search, or switch the availability filter back to All.'),
            'emptyCta' => __('Create a service'),
            'emptyTypeCta' => __('Create :type'),
            'clearFilters' => __('Clear filters'),
        ],
        'composer' => [
            'createTitle' => __('New service'),
            'editTitle' => __('Edit service'),
            'createSubtitle' => __('Pick a type, set who it is for, then add the price.'),
            'editSubtitle' => __('Update how this service appears to students.'),
            'typeLabel' => __('What are you selling?'),
            'pickTypeHint' => __('Choose a type to see its fields.'),
            'typeLocked' => __('The service type cannot be changed. Delete it and create a new one instead.'),
            'close' => __('Close'),
            'cancel' => __('Cancel'),
            'discardTitle' => __('Discard changes?'),
            'discardBody' => __('Your edits will be lost if you close this form.'),
            'discard' => __('Discard'),
            'keepEditing' => __('Keep editing'),
            'basicsTitle' => __('Service details'),
            'basicsHint' => __('This is what students read before they buy.'),
            'scopeTitle' => __('Who it is for'),
            'scopeHint' => __('Narrow the service to a grade, a subject or one lesson.'),
            'settingsTitle' => __('Delivery settings'),
            'settingsHint' => __('How much time, paper or turnaround this service includes.'),
            'requiredHint' => __('Fields marked * are required.'),
            'submitCreate' => __('Add service'),
            'submitSave' => __('Save changes'),
            'submitting' => __('Saving...'),
        ],
        'fields' => [
            'grade' => __('Grade'),
            'gradeAll' => __('All grades'),
            'gradePick' => __('Pick a grade'),
            'gradeHintRequired' => __('Required — lessons are per grade.'),
            'gradeHintOptional' => __('Optional — narrows the subjects below.'),
            'subject' => __('Subject'),
            'subjectPick' => __('Pick a subject'),
            'subjectNone' => __('No subject — lesson only'),
            'subjectHintRequired' => __('Required for this service.'),
            'subjectHintOptional' => __('Optional when the service is pinned to a lesson.'),
            'subjectHintQna' => __('Add a subject, or pin the session to one lesson below.'),
            'lesson' => __('Lesson'),
            'lessonPick' => __('Pick a lesson'),
            'lessonWhole' => __('Whole subject'),
            'lessonHintRequired' => __('Required for this service.'),
            'lessonHintOptional' => __('Optional — pin this service to one lesson.'),
            'title' => __('Title'),
            'titlePlaceholder' => __('e.g. O/L Maths crash plan'),
            'price' => __('Price (rupees)'),
            'description' => __('Description (optional)'),
            'descriptionPlaceholder' => __('What does the student get? Anything they should know before buying?'),
            'allocation' => __('Total time'),
            'allocationHint' => __('Students book this total time in sessions, at least 30 minutes.'),
            'hours' => __('hours'),
            'minutes' => __('minutes'),
            'session' => __('Session length'),
            'sessionNone' => __('No preference'),
            'sessionHint' => __('How long each live session runs.'),
            'maxSessions' => __('Session cap (optional)'),
            'maxSessionsPlaceholder' => __('e.g. 8'),
            'maxSessionsHint' => __('Leave empty for no cap.'),
            'maxPages' => __('Page limit (optional)'),
            'maxPagesPlaceholder' => __('Any length'),
            'turnaround' => __('Turnaround (hours)'),
            'turnaroundPlaceholder' => __('e.g. 72'),
            'turnaroundHint' => __('How long a student waits for their marked paper.'),
        ],
        'preview' => [
            'title' => __('Student preview'),
            'hint' => __('This is how the service appears in your catalog.'),
            'untitled' => __('Your service title'),
            'emptyScope' => __('Pick a scope to make it easier for students to find.'),
            'noDescription' => __('Add a short description so students know what to expect.'),
            'detail' => [
                'total' => __(':value total'),
                'session' => __(':value min session'),
                'pages' => __(':value pages'),
                'anyPages' => __('Any pages'),
                'turnaround' => __(':value h turnaround'),
            ],
        ],
        'errors' => [
            'title' => __('Some details need fixing'),
            'body' => __('Check the highlighted fields in the form.'),
            'duplicate' => __('You already offer this service for this scope.'),
        ],
        'delete' => [
            'title' => __('Delete this service?'),
            'body' => __('It will be removed from your catalog and students can no longer buy it.'),
            'blockedTitle' => __('This service cannot be deleted'),
            'blockedBody' => __('It already has purchases. Pause it instead to stop new sales.'),
            'cancel' => __('Keep service'),
            'confirm' => __('Delete service'),
        ],
        'toastLabel' => __('Notifications'),
        'toastDismiss' => __('Dismiss'),
    ];

    $config = [
        'appName' => config('app.name', 'Studylikepro'),
        'csrfToken' => csrf_token(),
        'theme' => ['preset' => $themePreset],
        'notices' => $notices,
        'hasSubjects' => $subjects->isNotEmpty(),
        'stats' => [
            'total' => $offeringsPayload->count(),
            'active' => $activeCount,
            'paused' => $pausedCount,
            'sold' => (int) $soldCount,
            'types' => $typesPayload->where('count', '>', 0)->count(),
        ],
        'types' => $typesPayload->all(),
        'offerings' => $offeringsPayload->all(),
        'options' => [
            'grades' => $gradeOptions,
            'subjects' => $subjectOptions,
            'lessons' => $lessonOptions,
            'durations' => $durations,
        ],
        'limits' => [
            'title' => 160,
            'description' => 2000,
            'priceMin' => 1,
            'priceMax' => 1000000,
            'allocationMin' => 30,
            'allocationMax' => 6000,
            'hoursMax' => 100,
            'maxSessionsMin' => 1,
            'maxSessionsMax' => 20,
            'maxPagesMin' => 1,
            'maxPagesMax' => 500,
            'turnaroundMin' => 1,
            'turnaroundMax' => 720,
        ],
        'currency' => [
            'symbol' => $currencySymbol,
            'code' => (string) config('studylikepro.currency'),
        ],
        'form' => $formValues === null ? null : [
            'type' => $postedType,
            'values' => $formValues,
        ],
        'openComposer' => $openComposer,
        'editingOfferingId' => $editingOfferingId > 0 ? $editingOfferingId : null,
        'errors' => $fieldErrors,
        'hasErrors' => $fieldErrors !== [],
        'urls' => [
            'store' => route('teacher.services.store'),
            'subjects' => route('teacher.subjects.index'),
        ],
        'copy' => $copy,
    ];

    // JSON_UNESCAPED_SLASHES keeps the URLs readable; the HEX flags keep the
    // island safe inside a <script> tag.
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Services & pricing') }}</h2>
    </x-slot>

    <script type="application/json" id="teacher-services-config">@json($config, $jsonFlags)</script>

    <div id="teacher-services-app"></div>

    {{-- Fallback for a browser without JavaScript: the same fields post to the
         same routes, but the grade/subject/lesson cascade is not filtered. --}}
    <noscript>
        <div class="space-y-6">
            @foreach ($config['notices'] as $notice)
                <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4 text-sm text-slate-700 dark:border-slate-800/80 dark:bg-slate-900/60 dark:text-slate-200" role="status">
                    <p class="font-semibold">{{ $notice['title'] }}</p>
                    <p>{{ $notice['message'] }}</p>
                </div>
            @endforeach

            @if (! $config['hasSubjects'])
                <div class="rounded-2xl border border-slate-200/80 bg-white p-6 text-center dark:border-slate-800/80 dark:bg-slate-900">
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $config['copy']['list']['noSubjectsBody'] }}</p>
                    <a href="{{ $config['urls']['subjects'] }}" class="mt-3 inline-block text-sm font-semibold text-primary">
                        {{ $config['copy']['list']['noSubjectsCta'] }}
                    </a>
                </div>
            @else
                @foreach ($types as $type)
                    @php $typeOfferings = $offerings->where('service_type', $type)->values(); @endphp

                    <div class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                        <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">
                            {{ $type->pluralLabel() }}
                            <span class="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">{{ $typeOfferings->count() }}</span>
                        </h3>
                        <p class="mt-1 text-xs text-slate-400">{{ $typeHints[$type->value] }}</p>

                        @include('teacher.services._form', [
                            'type' => $type,
                            'gradeOptions' => $gradeOptions,
                            'subjectOptions' => $subjectOptions,
                            'lessonOptions' => $lessonOptions,
                            'durations' => $durations,
                            'offering' => null,
                        ])

                        @foreach ($typeOfferings as $offering)
                            <div class="mt-4 rounded-xl border border-slate-200/80 p-4 dark:border-slate-800">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ $offering->title }}</p>
                                        <p class="mt-0.5 text-xs text-slate-400">
                                            {{ $offering->is_active ? __('Active') : __('Paused') }}
                                            @if ($offering->grade) · {{ $offering->grade->label }} @endif
                                            @if ($offering->subject) · {{ $offering->subject->name }} @endif
                                            @if ($offering->lesson) · {{ $offering->lesson->name }} @endif
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ $money((int) $offering->price_minor) }}</p>
                                        <p class="text-[11px] text-slate-400">{{ $priceSuffixes[$type->value] ?? __('per session') }}</p>
                                    </div>
                                </div>

                                <div class="mt-3 flex flex-wrap items-center gap-3">
                                    <details class="w-full">
                                        <summary class="cursor-pointer text-xs font-semibold text-primary">{{ __('Edit') }}</summary>
                                        <div class="mt-3">
                                            @include('teacher.services._form', [
                                                'type' => $type,
                                                'gradeOptions' => $gradeOptions,
                                                'subjectOptions' => $subjectOptions,
                                                'lessonOptions' => $lessonOptions,
                                                'durations' => $durations,
                                                'offering' => $offering,
                                            ])
                                        </div>
                                    </details>

                                    <form method="POST" action="{{ route('teacher.services.toggle', $offering) }}">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold text-primary">
                                            {{ $offering->is_active ? __('Pause') : __('Resume') }}
                                        </button>
                                    </form>

                                    <form method="POST" action="{{ route('teacher.services.destroy', $offering) }}">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs font-semibold text-red-600 dark:text-red-400">{{ __('Delete') }}</button>
                                    </form>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            @endif
        </div>
    </noscript>

    @vite('resources/js/teacher-services/main.js')
</x-app-layout>
