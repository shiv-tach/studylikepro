@php
    // The student subject page is rendered by resources/js/student-subjects.
    // Blade keeps ownership of the copy, the prices, the permission checks and
    // the route map, then hands them over as a JSON island.
    $user = Auth::user();
    $themePreset = $userTheme['preset'] ?? 'classic';

    $money = fn (int $minor) => platform_settings()->formatMinor($minor);

    $viewerTz = $user->studentProfile?->timezone
        ?? $user->teacherProfile?->timezone
        ?? config('studylikepro.default_display_timezone');

    // Signed-in students stay inside their workspace, so every teacher link is
    // the directory profile rather than the public one.
    $teacherUrl = fn ($teacher) => route('student.teachers.show', $teacher);
    $directoryUrl = route('student.teachers.index', array_filter(['subject' => $subject->slug]));
    $directoryRootUrl = route('student.teachers.index');
    $allGradesUrl = route('student.subjects.show', $subject);

    $serviceGroups = $offeringGroups->map(function (array $group) use ($money, $teacherUrl, $user) {
        $type = $group['type'];

        return [
            'key' => $type->value,
            'icon' => $type->icon(),
            'label' => $type->pluralLabel(),
            'items' => $group['offerings']->map(function ($offering) use ($type, $money, $teacherUrl, $user) {
                $teacher = $offering->teacherProfile;
                $ratingCount = (int) $teacher->rating_count;

                // Buying a paper service starts a marking order; every other
                // service goes straight to checkout. Students who cannot buy
                // it yet are pointed at the teacher instead.
                $action = match (true) {
                    ! $user->can('buy', $offering) => [
                        'kind' => 'link',
                        'variant' => 'outline',
                        'label' => __('View teacher'),
                        'url' => $teacherUrl($teacher),
                    ],
                    $type === \App\Enums\ServiceType::PaperMarking => [
                        'kind' => 'link',
                        'variant' => 'primary',
                        'label' => __('Submit a paper'),
                        'url' => route('student.marking.create', $offering),
                    ],
                    default => [
                        'kind' => 'form',
                        'variant' => 'primary',
                        'label' => __('Buy now'),
                        'url' => route('student.purchases.store', $offering),
                    ],
                };

                return [
                    'id' => $offering->id,
                    'title' => $offering->title,
                    'price' => $money($offering->price_minor),
                    'description' => $offering->description,
                    'teacher' => [
                        'name' => $teacher->user->name,
                        'initials' => (string) Str::of($teacher->user->name)->substr(0, 2),
                        'avatarUrl' => $teacher->user->avatarUrl(),
                        'url' => $teacherUrl($teacher),
                        'rating' => $ratingCount > 0 ? number_format((float) $teacher->rating_avg, 1) : null,
                        'ratingCount' => $ratingCount,
                    ],
                    'chips' => array_values(array_filter([
                        $offering->grade ? '🎓 '.$offering->grade->label : null,
                        $offering->lesson?->name,
                        $type === \App\Enums\ServiceType::Lesson && $offering->allocation_minutes
                            ? $offering->allocation_minutes.' '.__('min total')
                            : null,
                        $type->isLiveSession() && $offering->default_session_minutes
                            ? $offering->default_session_minutes.' '.__('min sessions')
                            : null,
                        $type === \App\Enums\ServiceType::PaperMarking
                            ? ($offering->max_pages ? $offering->max_pages.' '.__('pages') : __('Any pages'))
                            : null,
                        $type === \App\Enums\ServiceType::PaperMarking
                            ? $offering->turnaround_hours.__('h turnaround')
                            : null,
                    ])),
                    'action' => $action,
                ];
            })->values()->all(),
        ];
    })->values();

    $teacherCards = $teachers->map(function ($teacher) use ($subject, $grade, $gradesById, $nextSlots, $viewerTz, $money, $teacherUrl, $user) {
        $ratingCount = (int) $teacher->rating_count;
        $gradeScope = $teacher->gradeScopeLabelFor($subject, $gradesById);
        $lessonCount = (int) ($teacher->lessons_count ?? 0);

        $cardRate = $grade !== null
            ? $teacher->effectiveRateFor($subject, $grade->id)
            : $teacher->cheapestRateFor($subject);

        $rateFrom = $grade === null && $cardRate < $teacher->effectiveRateFor($subject);

        $nextSlot = $nextSlots[$teacher->id] ?? null;

        $meta = array_values(array_filter([
            $gradeScope,
            $lessonCount > 0 ? $lessonCount.' '.Str::plural('lesson', $lessonCount) : null,
        ]));

        return [
            'id' => $teacher->id,
            'name' => $teacher->user->name,
            'initials' => (string) Str::of($teacher->user->name)->substr(0, 2),
            'avatarUrl' => $teacher->user->avatarUrl(),
            'url' => $teacherUrl($teacher),
            'headline' => $teacher->headline,
            'rating' => $ratingCount > 0 ? number_format((float) $teacher->rating_avg, 1) : null,
            'ratingCount' => $ratingCount,
            'metaLabel' => $meta === [] ? null : implode(' · ', $meta),
            'rate' => $money($cardRate),
            'rateFrom' => $rateFrom,
            'nextSlot' => $nextSlot
                ? $nextSlot['starts_at']->setTimezone($viewerTz)->format('D d M · H:i')
                : null,
            'canBook' => $user->can('create', [\App\Models\Booking::class, $teacher]),
            'bookUrl' => route('student.bookings.create', [
                'teacherProfile' => $teacher,
                'subject_id' => $subject->id,
            ]),
        ];
    })->values();

    $lessonGroups = $lessons
        ->groupBy(fn ($lesson) => $lesson->grade?->label ?? __('Other'))
        ->map(fn ($group, $label) => [
            'label' => $label,
            'countLabel' => $group->count().' '.Str::plural('lesson', $group->count()),
            'items' => $group->map(fn ($lesson) => [
                'id' => $lesson->id,
                'name' => $lesson->name,
            ])->values()->all(),
        ])
        ->values();

    // The trail the page top renders: a student can always step back up the
    // tree (Dashboard → My subjects → this subject) on any screen size.
    $backLabel = __('My subjects');

    $breadcrumbs = [
        ['label' => __('Dashboard'), 'url' => route('student.dashboard')],
        ['label' => $backLabel, 'url' => route('student.interests.edit')],
        ['label' => $subject->name, 'url' => null],
    ];

    $subjectPayload = [
        'appName' => config('app.name', 'Studylikepro'),
        'csrfToken' => csrf_token(),
        'theme' => ['preset' => $themePreset],
        'subject' => [
            'name' => $subject->name,
            'icon' => $subject->icon ?? '📘',
            'description' => $subject->description,
            'lessonLabel' => $lessons->count().' '.Str::plural('lesson', $lessons->count()),
            'basket' => $subject->basket
                ? ['label' => trim(($subject->basket->icon ?? '').' '.$subject->basket->name)]
                : null,
            'grade' => $grade ? [
                'label' => $grade->label,
                'chip' => $level?->name ? $level->name.' · '.$grade->label : $grade->label,
                'allGradesUrl' => $allGradesUrl,
            ] : null,
        ],
        'copy' => [
            'back' => $backLabel,
            'pickOne' => __('pick one'),
            'viewAllGrades' => __('View all grades'),
            'servicesTitle' => $grade
                ? __('Services for :subject in :grade', ['subject' => $subject->name, 'grade' => $grade->label])
                : __('Services for :subject', ['subject' => $subject->name]),
            'servicesIntro' => __('Ready-made packages from verified teachers — buy one, or open a teacher to book a live lesson.'),
            'servicesEmpty' => __('No ready-made services for this subject yet — book a verified teacher for a live lesson instead.'),
            'browseTeachers' => __('Browse teachers'),
            'teachersTitle' => $grade
                ? __('Teachers who teach :subject in :grade', ['subject' => $subject->name, 'grade' => $grade->label])
                : __('Teachers who teach :subject', ['subject' => $subject->name]),
            'teachersAvailable' => __(':count verified teachers available', ['count' => $teacherTotal]),
            'seeAllTeachers' => __('See all :count teachers', ['count' => $teacherTotal]),
            'teachersEmpty' => $grade
                ? __('No verified teachers yet for :subject in :grade — we are onboarding more every week.', ['subject' => $subject->name, 'grade' => $grade->label])
                : __('No verified teachers yet for :subject — we are onboarding more every week.', ['subject' => $subject->name]),
            'browseAllTeachers' => __('Browse all teachers'),
            'newTeacher' => __('New teacher'),
            'verified' => __('Verified'),
            'bookNow' => __('Book now'),
            'viewProfile' => __('View profile'),
            'rateFrom' => __('from'),
            'perHour' => __('/hr'),
            'nextLabel' => __('Next:'),
            'noSlots' => __('No slots in the next 7 days'),
            'lessonsTitle' => __('Lessons'),
            'lessonsEmptyForGrade' => __('No :grade lessons for this subject yet.', ['grade' => $grade?->label]),
            'lessonsEmpty' => __('Lessons for this subject are being prepared.'),
            'ctaTitle' => __('Want help with :subject?', ['subject' => $subject->name]),
            'ctaBody' => __('Upload a question and we will match you with a verified teacher for a live lesson.'),
            'ctaAction' => __('Ask a question'),
        ],
        'serviceGroups' => $serviceGroups,
        'teachers' => [
            'items' => $teacherCards,
            'total' => $teacherTotal,
            'directoryUrl' => $directoryUrl,
            'directoryRootUrl' => $directoryRootUrl,
        ],
        'lessonGroups' => $lessonGroups,
        'breadcrumbs' => $breadcrumbs,
        'urls' => [
            'interests' => route('student.interests.edit'),
            'ask' => route('student.requests.create'),
        ],
    ];

    // JSON_UNESCAPED_SLASHES keeps the URLs readable; the HEX flags keep the
    // island safe inside a <script> tag.
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ $subject->name }}</h2>
            @if ($grade)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">
                    🎓 {{ $level?->name }} · {{ $grade->label }}
                </span>
            @endif
        </div>
    </x-slot>

    <script type="application/json" id="student-subject-config">@json($subjectPayload, $jsonFlags)</script>

    <div id="student-subject-app"></div>

    {{-- Fallback for a browser without JavaScript: the page is a thin shell, so
         list the lessons the Vue app would have rendered. --}}
    <noscript>
        <div class="mx-auto max-w-6xl space-y-6">
            <div class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ $subject->name }}</h3>
                @if ($lessons->isEmpty())
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Lessons for this subject are being prepared.') }}</p>
                @else
                    <ul class="mt-3 space-y-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
                        @foreach ($lessons as $lesson)
                            <li>• {{ $lesson->name }}</li>
                        @endforeach
                    </ul>
                @endif
                <p class="mt-4 text-sm">
                    <a class="font-semibold text-primary hover:underline" href="{{ route('student.teachers.index', array_filter(['subject' => $subject->slug])) }}">{{ __('Browse teachers') }}</a>
                    <span class="mx-2 text-slate-300">·</span>
                    <a class="font-semibold text-primary hover:underline" href="{{ route('student.interests.edit') }}">{{ __('My subjects') }}</a>
                </p>
            </div>
        </div>
    </noscript>

    @vite('resources/js/student-subjects/main.js')
</x-app-layout>
