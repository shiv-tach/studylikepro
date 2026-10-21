@php
    // /teacher/subjects is rendered by resources/js/teacher-subjects. Blade keeps
    // ownership of the approved subjects' grade/lesson/rate setup, the pending and
    // rejected queues, the catalog picker, the propose form, the validation errors
    // and the route map, then hands them over as a JSON island. The noscript block
    // below is the same flow without JavaScript.
    $themePreset = $userTheme['preset'] ?? 'classic';
    $currencySymbol = platform_settings()->currencySymbol();
    $baseRateMajor = (int) ($baseRateMinor / 100);
    $editingSubjectId = (int) old('editing_subject', 0);
    $selectedLessonIds = array_map('intval', $selectedLessons);
    $oldGrades = array_map('intval', (array) old('grades', []));
    $oldLessons = array_map('intval', (array) old('lessons', []));

    $isEditing = fn ($subjectId) => $editingSubjectId === (int) $subjectId;

    // Laravel attaches nested messages to the wildcard key (grades.0,
    // grade_rates.7, lessons.3), so both keys are merged for display.
    $fieldMessages = fn (string $key) => array_values(array_merge(
        $errors->get($key),
        $errors->get($key.'.*')
    ));

    $subjectsPayload = $subjects->map(function ($subject) use (
        $baseRateMajor,
        $editingSubjectId,
        $fieldMessages,
        $isEditing,
        $oldGrades,
        $oldLessons,
        $selectedLessonIds
    ) {
        $editing = $isEditing($subject->id);
        $pivot = $subject->pivot;

        $savedGradeIds = array_map('intval', $pivot->grade_levels ?? []);
        $savedGradeRates = collect($pivot->grade_rates ?? [])
            ->mapWithKeys(fn ($rate, $gradeId) => [(int) $gradeId => (int) $rate])
            ->all();
        $savedDefaultRate = $pivot->rate_per_hour_minor !== null
            ? (int) ($pivot->rate_per_hour_minor / 100)
            : null;

        // A rejected save replays what the teacher submitted for THIS subject
        // only — the hidden `editing_subject` field identifies it.
        $defaultRate = $editing ? old('rate_per_hour', $savedDefaultRate) : $savedDefaultRate;
        $defaultRate = $defaultRate === '' || $defaultRate === null ? null : (int) $defaultRate;

        $lessonGroups = $subject->lessons->groupBy('grade_id');

        $gradesPayload = ($subject->educationLevel?->grades ?? collect())
            ->map(function ($grade) use (
                $editing,
                $lessonGroups,
                $oldGrades,
                $oldLessons,
                $savedGradeIds,
                $savedGradeRates,
                $selectedLessonIds
            ) {
                $savedRate = $savedGradeRates[$grade->id] ?? null;
                $savedMajor = $savedRate !== null ? (int) ($savedRate / 100) : null;
                $rate = $editing ? old("grade_rates.{$grade->id}", $savedMajor) : $savedMajor;
                $rate = $rate === '' || $rate === null ? null : (int) $rate;

                $lessonsPayload = ($lessonGroups->get($grade->id) ?? collect())
                    ->map(function ($lesson) use ($editing, $oldLessons, $selectedLessonIds) {
                        $selected = $editing
                            ? in_array((int) $lesson->id, $oldLessons, true)
                            : in_array((int) $lesson->id, $selectedLessonIds, true);

                        return [
                            'id' => (int) $lesson->id,
                            'name' => $lesson->name,
                            'selected' => $selected,
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'id' => (int) $grade->id,
                    'label' => $grade->label,
                    'selected' => $editing
                        ? in_array((int) $grade->id, $oldGrades, true)
                        : in_array((int) $grade->id, $savedGradeIds, true),
                    'rate' => $rate,
                    'lessons' => $lessonsPayload,
                ];
            })
            ->values()
            ->all();

        $selectedGradeCount = count(array_filter($gradesPayload, fn ($grade) => $grade['selected']));
        $selectedLessonCount = collect($gradesPayload)
            ->filter(fn ($grade) => $grade['selected'])
            ->sum(fn ($grade) => count(array_filter($grade['lessons'], fn ($lesson) => $lesson['selected'])));
        $totalLessonCount = collect($gradesPayload)->sum(fn ($grade) => count($grade['lessons']));

        return [
            'id' => (int) $subject->id,
            'name' => $subject->name,
            'icon' => $subject->icon ?: '📘',
            'levelName' => $subject->educationLevel?->name,
            'levelIcon' => $subject->educationLevel?->icon,
            'updateUrl' => route('teacher.subjects.update', $subject),
            'defaultRate' => $defaultRate,
            'baseRatePlaceholder' => $baseRateMajor,
            'grades' => $gradesPayload,
            'counts' => [
                'selectedGrades' => $selectedGradeCount,
                'selectedLessons' => (int) $selectedLessonCount,
                'totalLessons' => (int) $totalLessonCount,
            ],
            'isEditing' => $editing,
            'errors' => $editing ? [
                'grades' => $fieldMessages('grades'),
                'lessons' => $fieldMessages('lessons'),
                'gradeRates' => $fieldMessages('grade_rates'),
                'rate' => $fieldMessages('rate_per_hour'),
            ] : [
                'grades' => [],
                'lessons' => [],
                'gradeRates' => [],
                'rate' => [],
            ],
        ];
    })->values();

    $pendingPayload = collect();

    foreach ($pendingSubjects as $pendingSubject) {
        $pendingPayload->push([
            'key' => 'subject-'.$pendingSubject->id,
            'kind' => 'subject',
            'name' => $pendingSubject->name,
            'icon' => $pendingSubject->icon ?: '📘',
            'levelName' => $pendingSubject->educationLevel?->name,
            'statusLabel' => $pendingSubject->pivot->status->label(),
            'statusTone' => 'amber',
            'isNew' => false,
            'requestedAt' => null,
            'cancelUrl' => null,
        ]);
    }

    foreach ($pendingRequests as $subjectRequest) {
        $pendingPayload->push([
            'key' => 'request-'.$subjectRequest->id,
            'kind' => 'request',
            'name' => $subjectRequest->subject?->name ?? $subjectRequest->proposed_name,
            'icon' => $subjectRequest->subject?->icon ?: '🆕',
            'levelName' => $subjectRequest->educationLevel?->name ?? $subjectRequest->subject?->educationLevel?->name,
            'statusLabel' => $subjectRequest->status->label(),
            'statusTone' => 'amber',
            'isNew' => $subjectRequest->isNewSubject(),
            'requestedAt' => $subjectRequest->created_at?->diffForHumans(),
            'cancelUrl' => route('teacher.subjects.request.cancel', $subjectRequest),
        ]);
    }

    $rejectedPayload = collect();

    foreach ($rejectedSubjects as $rejectedSubject) {
        $rejectedPayload->push([
            'key' => 'subject-'.$rejectedSubject->id,
            'kind' => 'subject',
            'name' => $rejectedSubject->name,
            'icon' => $rejectedSubject->icon ?: '📘',
            'levelName' => $rejectedSubject->educationLevel?->name,
            'statusLabel' => $rejectedSubject->pivot->status->label(),
            'statusTone' => 'rose',
            'note' => null,
            'reapplySection' => 'catalog',
            'reapplyQuery' => $rejectedSubject->name,
            'reapplyLevelKey' => $rejectedSubject->educationLevel?->key,
            'reapplyLevelId' => null,
        ]);
    }

    foreach ($rejectedRequests as $subjectRequest) {
        $isNew = $subjectRequest->isNewSubject();

        $rejectedPayload->push([
            'key' => 'request-'.$subjectRequest->id,
            'kind' => 'request',
            'name' => $subjectRequest->subject?->name ?? $subjectRequest->proposed_name,
            'icon' => $subjectRequest->subject?->icon ?: '🆕',
            'levelName' => $subjectRequest->educationLevel?->name ?? $subjectRequest->subject?->educationLevel?->name,
            'statusLabel' => $subjectRequest->status->label(),
            'statusTone' => 'rose',
            'note' => $subjectRequest->admin_note,
            'reapplySection' => $isNew ? 'propose' : 'catalog',
            'reapplyQuery' => $subjectRequest->proposed_name ?? $subjectRequest->subject?->name,
            'reapplyLevelKey' => $subjectRequest->subject?->educationLevel?->key,
            'reapplyLevelId' => $subjectRequest->education_level_id,
        ]);
    }

    // The catalog picker only offers levels that still have unclaimed subjects.
    $addGroups = $availableSubjects->groupBy('education_level_id');
    $catalogLevels = $levels
        ->filter(fn ($level) => $addGroups->has($level->id))
        ->values()
        ->map(fn ($level) => [
            'key' => $level->key,
            'name' => $level->name,
            'icon' => $level->icon,
            'subjects' => $addGroups->get($level->id, collect())
                ->map(fn ($subject) => [
                    'id' => (int) $subject->id,
                    'name' => $subject->name,
                    'icon' => $subject->icon ?: '📘',
                ])
                ->values()
                ->all(),
        ])
        ->values();

    $proposeErrors = [
        'name' => array_values($errors->get('proposed_name')),
        'levelId' => array_values($errors->get('education_level_id')),
        'note' => array_values($errors->get('note')),
    ];

    $subjectIdErrors = $fieldMessages('subject_ids');

    $hasSetupErrors = $subjectsPayload->contains(fn ($subject) => $subject['isEditing'] && (
        $subject['errors']['grades'] !== []
        || $subject['errors']['lessons'] !== []
        || $subject['errors']['gradeRates'] !== []
        || $subject['errors']['rate'] !== []
    ));

    $hasProposeInput = session()->hasOldInput() && old('proposed_name') !== null;
    $savedSubjectId = (int) session('saved_subject', 0);

    // A successful save reopens the card it came from; a rejected one does too,
    // and is scrolled into view by the Vue app.
    $openSubjectId = $editingSubjectId > 0 ? $editingSubjectId : ($savedSubjectId > 0 ? $savedSubjectId : null);

    $initialTab = match (true) {
        $hasSetupErrors, $editingSubjectId > 0, $savedSubjectId > 0 => 'my',
        $hasProposeInput, $proposeErrors['name'] !== [] => 'add',
        $subjectIdErrors !== [] => 'add',
        $subjectsPayload->isEmpty() => 'add',
        default => 'my',
    };

    $addSection = ($hasProposeInput || $proposeErrors['name'] !== []) ? 'propose' : 'catalog';

    // Every flash message becomes a toast, so a confirmation is seen wherever
    // the teacher is scrolled to after the redirect.
    $notices = [];

    if (session('status') === 'subjects-requested') {
        $notices[] = [
            'tone' => 'emerald',
            'title' => __('Requests sent'),
            'message' => __('Subject requests sent. Our team reviews them shortly and you will be notified once they are approved.'),
        ];
    } elseif (session('status') === 'subject-request-created') {
        $notices[] = [
            'tone' => 'emerald',
            'title' => __('Request sent'),
            'message' => __('Request sent. Our team reviews new subject proposals and will notify you once yours is approved.'),
        ];
    } elseif (session('status') === 'subject-request-cancelled') {
        $notices[] = [
            'tone' => 'slate',
            'title' => __('Request cancelled'),
            'message' => __('Request cancelled.'),
        ];
    } elseif (session('status') === 'no-new-subjects') {
        $notices[] = [
            'tone' => 'amber',
            'title' => __('Nothing new to add'),
            'message' => __('Those subjects are already on your list or already requested.'),
        ];
    } elseif (session('status') === 'subject-setup-updated') {
        $notices[] = [
            'tone' => 'emerald',
            'title' => __('Setup saved'),
            'message' => __('Teaching preferences saved.'),
        ];
    }

    $copy = [
        'hero' => [
            'eyebrow' => __('Teaching setup'),
            'title' => __('Subjects you teach'),
            'intro' => __('Pick the subjects you teach, the grades you cover and your hourly rate. Students only see what you have set up here.'),
            'baseRate' => __('Your base rate'),
            'baseRateHint' => __('The fallback for any grade without a rate of its own.'),
        ],
        'stats' => [
            'approved' => __('Approved subjects'),
            'awaiting' => __('Awaiting approval'),
            'grades' => __('Grades covered'),
            'lessons' => __('Lessons selected'),
        ],
        'tabs' => [
            'label' => __('Subject sections'),
            'subjects' => __('My subjects'),
            'requests' => __('Requests'),
            'add' => __('Add subjects'),
        ],
        'empty' => [
            'title' => __('No approved subjects yet'),
            'body' => __('Add subjects from the catalog or request new ones. Once our team approves them, you can choose lessons, grades and rates here.'),
            'cta' => __('Add your first subject'),
        ],
        'subject' => [
            'gradesCountOne' => __(':selected of :total grade'),
            'gradesCount' => __(':selected of :total grades'),
            'lessonsCountOne' => __(':selected lesson selected'),
            'lessonsCount' => __(':selected lessons selected'),
            'defaultRate' => __('Default :rate'),
            'noDefaultRate' => __('No default rate'),
            'baseRateFallback' => __('Base rate :rate'),
            'edit' => __('Edit setup'),
            'hide' => __('Hide setup'),
            'needGrades' => __('Pick at least one grade'),
        ],
        'grades' => [
            'step' => __('Step 1'),
            'title' => __('Grades you support & rate'),
            'hint' => __('Tick every grade you teach and set its hourly rate. A grade without a rate uses the default rate.'),
            'rateFor' => __('Hourly rate for :grade'),
            'noGrades' => __('This subject has no grades in the catalog yet.'),
        ],
        'lessons' => [
            'step' => __('Step 2'),
            'title' => __('Lessons you teach'),
            'hint' => __('Every lesson of the grades you support is ticked by default — untick only what you do not cover.'),
            'selectAll' => __('Select all'),
            'clear' => __('Clear'),
            'gradeDisabled' => __('Tick this grade first'),
            'noneForSubject' => __('No lessons in the catalog for this subject yet.'),
            'noneForGrade' => __('No lessons for this grade yet.'),
        ],
        'rate' => [
            'step' => __('Step 3'),
            'title' => __('Default rate (optional)'),
            'hint' => __('Charged for any grade without a rate of its own. Leave empty to use your base rate of :rate per hour.'),
            'placeholder' => __('Default rate'),
        ],
        'save' => [
            'save' => __('Save this subject'),
            'saving' => __('Saving...'),
            'saved' => __('All changes saved.'),
            'unsaved' => __('Unsaved changes'),
            'reset' => __('Reset'),
            'counts' => __(':selected/:total lessons · :grades/:totalGrades grades'),
        ],
        'errors' => [
            'title' => __('Please fix the following:'),
            'saveFailed' => __('We could not save :subject. Check the highlighted fields below.'),
        ],
        'requests' => [
            'pendingTitle' => __('Waiting for review'),
            'pendingHint' => __('Our team approves these before students can find you through them.'),
            'rejectedTitle' => __('Not approved'),
            'rejectedHint' => __('These were not approved. Request one again below, or contact support if you need more detail.'),
            'withdraw' => __('Withdraw'),
            'confirmWithdraw' => __('Confirm withdraw'),
            'keep' => __('Keep'),
            'newSubject' => __('New subject'),
            'requestedAt' => __('Requested :when'),
            'reapply' => __('Request again'),
            'adminNote' => __('Admin note'),
            'empty' => __('Nothing is waiting for review right now.'),
        ],
        'add' => [
            'title' => __('Add a subject'),
            'hint' => __('Pick catalog subjects to add to your profile — every one needs admin approval before students can find you through it.'),
            'catalogTab' => __('From the catalog'),
            'proposeTab' => __('Propose a new subject'),
            'search' => __('Search subjects'),
            'selectAll' => __('Select all'),
            'clear' => __('Clear selection'),
            'noMatches' => __('No subjects match your search.'),
            'allOwned' => __('You already have every catalog subject — propose something new instead.'),
            'selectedOne' => __(':count subject selected'),
            'selected' => __(':count subjects selected'),
            'levelCount' => __(':count available'),
            'requestCta' => __('Request subjects'),
            'requestSelectedOne' => __('Request :count subject'),
            'requestSelected' => __('Request :count subjects'),
        ],
        'propose' => [
            'title' => __('Request a new subject'),
            'hint' => __("Can't find what you teach? Propose it — if the team approves, it is added to the catalog and assigned to you."),
            'nameLabel' => __('Subject name'),
            'namePlaceholder' => __('e.g. Advanced ICT'),
            'levelLabel' => __('Level'),
            'levelPlaceholder' => __('Choose a level'),
            'noteLabel' => __('Why do you want to teach it? (optional)'),
            'notePlaceholder' => __('Your background, syllabus, experience...'),
            'submit' => __('Send request'),
            'charsLeft' => __(':count characters left'),
        ],
    ];

    $config = [
        'appName' => config('app.name', 'Studylikepro'),
        'csrfToken' => csrf_token(),
        'theme' => ['preset' => $themePreset],
        'notices' => $notices,
        'teacher' => [
            'baseRateMinor' => $baseRateMinor,
            'baseRateLabel' => platform_settings()->formatMinor($baseRateMinor),
            'baseRateMajor' => $baseRateMajor,
            'currencySymbol' => $currencySymbol,
        ],
        'stats' => [
            'approved' => $subjectsPayload->count(),
            'awaiting' => $pendingPayload->count(),
            'grades' => $subjectsPayload->sum(fn ($subject) => $subject['counts']['selectedGrades']),
            'lessons' => $subjectsPayload->sum(fn ($subject) => $subject['counts']['selectedLessons']),
            'totalLessons' => $subjectsPayload->sum(fn ($subject) => $subject['counts']['totalLessons']),
            'labels' => [
                'approved' => trans_choice(
                    ':count approved subject|:count approved subjects',
                    $subjectsPayload->count()
                ),
                'awaiting' => trans_choice(
                    ':count awaiting approval|:count awaiting approval',
                    $pendingPayload->count()
                ),
                'grades' => trans_choice(
                    ':count grade covered|:count grades covered',
                    $subjectsPayload->sum(fn ($subject) => $subject['counts']['selectedGrades'])
                ),
                'lessons' => trans_choice(
                    ':count lesson selected|:count lessons selected',
                    $subjectsPayload->sum(fn ($subject) => $subject['counts']['selectedLessons'])
                ),
            ],
        ],
        'subjects' => $subjectsPayload->all(),
        'pending' => $pendingPayload->values()->all(),
        'rejected' => $rejectedPayload->values()->all(),
        'catalog' => [
            'levels' => $catalogLevels->all(),
            'defaultLevelKey' => $catalogLevels->isNotEmpty() ? $catalogLevels->first()['key'] : null,
            'hasSubjects' => $catalogLevels->isNotEmpty(),
        ],
        'propose' => [
            'levels' => $levels->map(fn ($level) => [
                'id' => (int) $level->id,
                'name' => $level->name,
                'icon' => $level->icon,
            ])->values()->all(),
            'limits' => ['name' => 100, 'note' => 500],
            'values' => [
                'name' => (string) old('proposed_name', ''),
                'levelId' => old('education_level_id') === null || old('education_level_id') === ''
                    ? null
                    : (int) old('education_level_id'),
                'note' => (string) old('note', ''),
            ],
            'errors' => $proposeErrors,
        ],
        'subjectIdErrors' => $subjectIdErrors,
        'editingSubjectId' => $editingSubjectId > 0 ? $editingSubjectId : null,
        'openSubjectId' => $openSubjectId,
        'initialTab' => $initialTab,
        'addSection' => $addSection,
        'urls' => [
            'add' => route('teacher.subjects.add'),
            'request' => route('teacher.subjects.request'),
        ],
        'copy' => $copy,
    ];

    // JSON_UNESCAPED_SLASHES keeps the URLs readable; the HEX flags keep the
    // island safe inside a <script> tag.
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Subjects') }}</h2>
    </x-slot>

    <script type="application/json" id="teacher-subjects-config">@json($config, $jsonFlags)</script>

    <div id="teacher-subjects-app"></div>

    {{-- Fallback for a browser without JavaScript: the same forms, field names
         and values post to the same routes. --}}
    <noscript>
        <div class="space-y-6">
            @foreach ($config['notices'] as $notice)
                <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4 text-sm text-slate-700 dark:border-slate-800/80 dark:bg-slate-900/60 dark:text-slate-200" role="status">
                    <p class="font-semibold">{{ $notice['title'] }}</p>
                    <p>{{ $notice['message'] }}</p>
                </div>
            @endforeach

            @if ($config['subjects'] === [])
                <div class="rounded-2xl border border-slate-200/80 bg-white p-6 text-center dark:border-slate-800/80 dark:bg-slate-900">
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $config['copy']['empty']['body'] }}</p>
                </div>
            @endif

            @foreach ($config['subjects'] as $subject)
                <div class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-lg">{{ $subject['icon'] }}</span>
                            <div>
                                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ $subject['name'] }}</h3>
                                @if ($subject['levelName'])
                                    <p class="text-xs text-slate-400">{{ $subject['levelName'] }}</p>
                                @endif
                            </div>
                        </div>
                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                            {{ $subject['counts']['selectedLessons'] }} / {{ $subject['counts']['totalLessons'] }} lessons selected
                        </span>
                    </div>

                    <form method="POST" action="{{ $subject['updateUrl'] }}" class="mt-5 space-y-5">
                        @csrf
                        <input type="hidden" name="sync_grades" value="1">
                        <input type="hidden" name="editing_subject" value="{{ $subject['id'] }}">

                        <div>
                            <p class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $config['copy']['grades']['title'] }}</p>
                            <p class="mt-1 text-xs text-slate-400">{{ $config['copy']['grades']['hint'] }}</p>
                            <div class="mt-3 space-y-2">
                                @foreach ($subject['grades'] as $grade)
                                    <label class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-slate-200/80 px-3 py-1.5 text-sm text-slate-600 dark:border-slate-800 dark:text-slate-300">
                                        <span class="flex items-center gap-2">
                                            <input type="checkbox" name="grades[]" value="{{ $grade['id'] }}" @checked($grade['selected']) class="rounded border-slate-300 text-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900">
                                            {{ $grade['label'] }}
                                        </span>
                                        <span class="relative">
                                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-xs text-slate-400">{{ $config['teacher']['currencySymbol'] }}</span>
                                            <input type="number" name="grade_rates[{{ $grade['id'] }}]" min="100" max="100000" step="50"
                                                   value="{{ $grade['rate'] }}" placeholder="{{ $subject['baseRatePlaceholder'] }}"
                                                   class="w-28 rounded-lg border-slate-300 bg-white py-1.5 pl-7 pr-2 text-right text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900" />
                                        </span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <p class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $config['copy']['lessons']['title'] }}</p>
                            <p class="mt-1 text-xs text-slate-400">{{ $config['copy']['lessons']['hint'] }}</p>
                            @php $hasLessons = collect($subject['grades'])->contains(fn ($grade) => $grade['lessons'] !== []); @endphp
                            @if (! $hasLessons)
                                <p class="mt-3 text-sm text-slate-400">{{ $config['copy']['lessons']['noneForSubject'] }}</p>
                            @endif
                            <div class="mt-3 space-y-3">
                                @foreach ($subject['grades'] as $grade)
                                    @continue($grade['lessons'] === [])
                                    <div>
                                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">{{ $grade['label'] }}</p>
                                        <div class="mt-1 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                                            @foreach ($grade['lessons'] as $lesson)
                                                <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200/80 px-3 py-2 text-sm text-slate-700 dark:border-slate-800 dark:text-slate-300">
                                                    <input type="checkbox" name="lessons[]" value="{{ $lesson['id'] }}" @checked($lesson['selected']) class="rounded border-slate-300 text-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900">
                                                    {{ $lesson['name'] }}
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <label for="rate-{{ $subject['id'] }}" class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $config['copy']['rate']['title'] }}</label>
                            <div class="relative mt-1">
                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-slate-400">{{ $config['teacher']['currencySymbol'] }}</span>
                                <input id="rate-{{ $subject['id'] }}" name="rate_per_hour" type="number" min="100" max="100000"
                                       value="{{ $subject['defaultRate'] }}" placeholder="{{ $config['copy']['rate']['placeholder'] }}"
                                       class="block w-full rounded-xl border-slate-300 bg-white pl-8 text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900" />
                            </div>
                            <p class="mt-1 text-xs text-slate-400">{{ str_replace(':rate', $config['teacher']['baseRateLabel'], $config['copy']['rate']['hint']) }}</p>
                        </div>

                        <div class="flex justify-end">
                            <x-primary-button>{{ $config['copy']['save']['save'] }}</x-primary-button>
                        </div>
                    </form>
                </div>
            @endforeach

            @if ($config['pending'] !== [])
                <div class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ $config['copy']['requests']['pendingTitle'] }}</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $config['copy']['requests']['pendingHint'] }}</p>
                    <ul class="mt-4 space-y-2">
                        @foreach ($config['pending'] as $item)
                            <li class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200/80 px-4 py-3 dark:border-slate-800">
                                <span class="flex flex-wrap items-center gap-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200">
                                    <span aria-hidden="true">{{ $item['icon'] }}</span>
                                    {{ $item['name'] }}
                                    @if ($item['isNew'])
                                        <span class="rounded-full bg-primary/10 px-2.5 py-0.5 text-[11px] font-semibold text-primary">{{ $config['copy']['requests']['newSubject'] }}</span>
                                    @endif
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">{{ $item['statusLabel'] }}</span>
                                </span>
                                @if ($item['cancelUrl'])
                                    <form method="POST" action="{{ $item['cancelUrl'] }}">
                                        @csrf
                                        <button type="submit" class="text-xs font-semibold text-slate-400 transition-colors hover:text-rose-600 dark:hover:text-rose-400">{{ $config['copy']['requests']['withdraw'] }}</button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($config['rejected'] !== [])
                <div class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ $config['copy']['requests']['rejectedTitle'] }}</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $config['copy']['requests']['rejectedHint'] }}</p>
                    <ul class="mt-4 space-y-2">
                        @foreach ($config['rejected'] as $item)
                            <li class="rounded-xl border border-rose-200/60 px-4 py-3 dark:border-rose-900/40">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <span class="flex flex-wrap items-center gap-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200">
                                        <span aria-hidden="true">{{ $item['icon'] }}</span>
                                        {{ $item['name'] }}
                                        <span class="inline-flex items-center rounded-full bg-rose-100 px-2.5 py-0.5 text-[11px] font-semibold text-rose-700 dark:bg-rose-900/30 dark:text-rose-400">{{ $item['statusLabel'] }}</span>
                                    </span>
                                </div>
                                @if ($item['note'])
                                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ $config['copy']['requests']['adminNote'] }}: {{ $item['note'] }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($config['catalog']['hasSubjects'])
                <div class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ $config['copy']['add']['title'] }}</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $config['copy']['add']['hint'] }}</p>

                    <form method="POST" action="{{ $config['urls']['add'] }}" class="mt-5 space-y-5">
                        @csrf
                        @foreach ($config['catalog']['levels'] as $level)
                            <div>
                                <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">
                                    <span aria-hidden="true">{{ $level['icon'] }}</span> {{ $level['name'] }}
                                </p>
                                <div class="mt-2 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                    @foreach ($level['subjects'] as $available)
                                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200/80 px-4 py-3 dark:border-slate-800">
                                            <input type="checkbox" name="subject_ids[]" value="{{ $available['id'] }}" class="rounded border-slate-300 text-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900">
                                            <span class="text-lg" aria-hidden="true">{{ $available['icon'] }}</span>
                                            <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $available['name'] }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach

                        @if ($config['subjectIdErrors'] !== [])
                            <ul class="space-y-1" role="alert">
                                @foreach ($config['subjectIdErrors'] as $message)
                                    <li class="text-sm font-medium text-rose-600 dark:text-rose-400">{{ $message }}</li>
                                @endforeach
                            </ul>
                        @endif

                        <div class="flex justify-end">
                            <x-primary-button>{{ $config['copy']['add']['requestCta'] }}</x-primary-button>
                        </div>
                    </form>
                </div>
            @endif

            <div class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ $config['copy']['propose']['title'] }}</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $config['copy']['propose']['hint'] }}</p>

                <form method="POST" action="{{ $config['urls']['request'] }}" class="mt-5 space-y-4">
                    @csrf
                    <div>
                        <x-input-label for="proposed_name" :value="$config['copy']['propose']['nameLabel']" />
                        <input id="proposed_name" name="proposed_name" type="text" maxlength="100"
                               value="{{ $config['propose']['values']['name'] }}"
                               placeholder="{{ $config['copy']['propose']['namePlaceholder'] }}"
                               class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900" />
                        <x-input-error :messages="$config['propose']['errors']['name']" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="education_level_id" :value="$config['copy']['propose']['levelLabel']" />
                        <select id="education_level_id" name="education_level_id"
                                class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900">
                            <option value="">{{ $config['copy']['propose']['levelPlaceholder'] }}</option>
                            @foreach ($config['propose']['levels'] as $level)
                                <option value="{{ $level['id'] }}" @selected((int) $config['propose']['values']['levelId'] === $level['id'])>{{ $level['name'] }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$config['propose']['errors']['levelId']" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="note" :value="$config['copy']['propose']['noteLabel']" />
                        <textarea id="note" name="note" rows="3" maxlength="500" placeholder="{{ $config['copy']['propose']['notePlaceholder'] }}"
                                  class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900">{{ $config['propose']['values']['note'] }}</textarea>
                        <x-input-error :messages="$config['propose']['errors']['note']" class="mt-2" />
                    </div>

                    <div class="flex justify-end">
                        <x-primary-button>{{ $config['copy']['propose']['submit'] }}</x-primary-button>
                    </div>
                </form>
            </div>
        </div>
    </noscript>

    @vite('resources/js/teacher-subjects/main.js')
</x-app-layout>
