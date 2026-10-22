@php
    // The public teacher directory is rendered by resources/js/public. Blade
    // owns the copy, the route map, the cascade of the pickers and every display
    // string (rates, next slots, pagination URLs), then hands them over as a
    // JSON island.
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);
    $currency = platform_settings()->currencySymbol();

    $viewerTz = auth()->user()?->studentProfile?->timezone
        ?? auth()->user()?->teacherProfile?->timezone
        ?? config('studylikepro.default_display_timezone');

    $selectedLevel = $levels->firstWhere('key', $filters['level'] ?? null);
    $selectedSubject = $subjects->firstWhere('slug', $filters['subject'] ?? null);

    // The pickers cascade server-side: level narrows subjects, subject narrows
    // lessons. Lesson values are ids because lesson slugs repeat per grade.
    $subjectOptions = $selectedLevel
        ? $subjects->where('education_level_id', $selectedLevel->id)
        : $subjects;

    $lessonGroups = ($selectedSubject ? $subjectOptions->where('id', $selectedSubject->id) : $subjectOptions)
        ->map(fn ($subject) => ['subject' => $subject, 'lessons' => $subject->lessons])
        ->filter(fn (array $group) => $group['lessons']->isNotEmpty());

    $gradeOptions = $selectedSubject
        ? $selectedSubject->lessons->pluck('grade')->filter()->unique('id')->sortBy('sort_order')->values()
        : ($selectedLevel ? $selectedLevel->grades : $levels->flatMap(fn ($level) => $level->grades));

    $gradeGroups = $levels
        ->map(fn ($level) => ['level' => $level, 'grades' => $gradeOptions->where('education_level_id', $level->id)])
        ->filter(fn (array $group) => $group['grades']->isNotEmpty());

    $cardGradeId = filled($filters['grade'] ?? null) ? (int) $filters['grade'] : null;

    $teacherCards = $teachers->map(function ($teacher) use ($selectedSubject, $cardGradeId, $money, $viewerTz, $nextSlots, $gradesById) {
        $nextSlot = $nextSlots[$teacher->id] ?? null;
        $ratingCount = (int) $teacher->rating_count;
        $gradeLabel = $teacher->gradeScopeLabel($gradesById);
        $lessonCount = (int) ($teacher->lessons_count ?? 0);

        $cardRate = match (true) {
            $selectedSubject !== null && $cardGradeId !== null => $teacher->effectiveRateFor($selectedSubject, $cardGradeId),
            $selectedSubject !== null => $teacher->cheapestRateFor($selectedSubject),
            default => $teacher->startingRateMinor($cardGradeId),
        };

        $meta = array_values(array_filter([
            $gradeLabel,
            $lessonCount > 0 ? $lessonCount.' '.Str::plural('lesson', $lessonCount) : null,
        ]));

        return [
            'id' => $teacher->id,
            'name' => $teacher->user->name,
            'initials' => (string) Str::of($teacher->user->name)->substr(0, 2),
            'avatarUrl' => $teacher->user->avatarUrl(),
            'headline' => $teacher->headline,
            'url' => route('teachers.show', $teacher),
            'rating' => $ratingCount > 0 ? number_format((float) $teacher->rating_avg, 1) : null,
            'ratingCount' => $ratingCount,
            'subjects' => $teacher->subjects->take(3)->map(fn ($subject) => [
                'label' => trim(($subject->icon ?? '').' '.$subject->name),
            ])->values(),
            'extraSubjects' => max(0, $teacher->subjects->count() - 3),
            'metaLabel' => $meta === [] ? null : implode(' · ', $meta),
            'rate' => $money($cardRate),
            'nextSlot' => $nextSlot
                ? $nextSlot['starts_at']->setTimezone($viewerTz)->format('D d M · H:i')
                : null,
        ];
    })->values();

    $pagination = $teachers->linkCollection()->map(fn (array $link) => [
        'label' => trim(html_entity_decode(strip_tags((string) $link['label']))),
        'url' => $link['url'] !== null ? html_entity_decode((string) $link['url']) : null,
        'active' => (bool) $link['active'],
    ])->values();

    $config = [
        'appName' => config('app.name', 'Studylikepro'),
        'authenticated' => auth()->check(),
        'copy' => [
            'eyebrow' => __('Verified teachers'),
            'title' => __('Find your teacher'),
            'intro' => __('Every teacher here is verified by our team. Pick your level and subject to see the grades and lessons they teach.'),
            'filtersTitle' => __('Filter the directory'),
            'filtersToggle' => __('Filters'),
            'filtersHide' => __('Hide filters'),
            'level' => __('Level'),
            'subject' => __('Subject'),
            'lesson' => __('Lesson'),
            'grade' => __('Grade'),
            'language' => __('Language'),
            'minRate' => __('Min :currency/hr', ['currency' => $currency]),
            'maxRate' => __('Max :currency/hr', ['currency' => $currency]),
            'weekday' => __('Available on'),
            'from' => __('From'),
            'to' => __('To'),
            'sort' => __('Sort by'),
            'apply' => __('Show teachers'),
            'clear' => __('Clear filters'),
            'anyLevel' => __('Any level'),
            'anySubject' => __('Any subject'),
            'anyLesson' => __('Any lesson'),
            'anyGrade' => __('Any grade'),
            'anyLanguage' => __('Any language'),
            'anyDay' => __('Any day'),
            'found' => trans_choice(':count teacher found|:count teachers found', $teachers->total()),
            'noResults' => __('No verified teachers match those filters yet. Try widening your search.'),
            'newTeacher' => __('New teacher'),
            'verified' => __('Verified'),
            'noSlots' => __('No slots in the next 7 days'),
            'nextLabel' => __('Next:'),
            'perHour' => __('/hr'),
            'view' => __('View'),
            'signInTitle' => __('Sign in to book a lesson'),
            'signInBody' => __('Create a free student account to book lessons, chat with teachers, and manage your bookings.'),
            'getStarted' => __('Get started free'),
            'logIn' => __('Log in'),
            'navSubjects' => __('Subjects'),
            'navTeachers' => __('Teachers'),
            'navContact' => __('Contact'),
            'resultsTitle' => $selectedSubject
                ? __('Teachers for :subject', ['subject' => $selectedSubject->name])
                : __('All verified teachers'),
        ],
        'filters' => [
            'level' => $filters['level'] ?? null,
            'subject' => $filters['subject'] ?? null,
            'lesson' => isset($filters['lesson']) ? (string) $filters['lesson'] : null,
            'grade' => isset($filters['grade']) ? (string) $filters['grade'] : null,
            'language' => $filters['language'] ?? null,
            'min_rate' => $filters['min_rate'] ?? null,
            'max_rate' => $filters['max_rate'] ?? null,
            'weekday' => isset($filters['weekday']) ? (string) $filters['weekday'] : null,
            'time_from' => $filters['time_from'] ?? null,
            'time_to' => $filters['time_to'] ?? null,
            'sort' => $sort,
        ],
        'levelOptions' => $levels->map(fn ($level) => [
            'value' => $level->key,
            'label' => trim(($level->icon ?? '').' '.$level->name),
        ])->values(),
        'subjectOptions' => $subjectOptions->map(fn ($subject) => [
            'value' => $subject->slug,
            'label' => trim(($subject->icon ?? '').' '.$subject->name)
                .(! $selectedLevel && $subject->educationLevel ? ' — '.$subject->educationLevel->name : ''),
        ])->values(),
        'lessonGroups' => $lessonGroups->map(fn (array $group) => [
            'label' => $group['subject']->name
                .(! $selectedLevel && $group['subject']->educationLevel ? ' — '.$group['subject']->educationLevel->name : ''),
            'options' => $group['lessons']->map(fn ($lesson) => [
                'value' => (string) $lesson->id,
                'label' => $lesson->name.($lesson->grade ? ' · '.$lesson->grade->label : ''),
            ])->values(),
        ])->values(),
        'gradeGroups' => $gradeGroups->map(fn (array $group) => [
            'label' => $group['level']->name,
            'options' => $group['grades']->map(fn ($grade) => [
                'value' => (string) $grade->id,
                'label' => $grade->label,
            ])->values(),
        ])->values(),
        'languageOptions' => collect(config('studylikepro.languages'))->values(),
        'weekdayOptions' => collect(config('studylikepro.weekdays'))->map(fn ($label, $day) => [
            'value' => (string) $day,
            'label' => $label,
        ])->values(),
        'sortOptions' => [
            ['value' => 'rating', 'label' => __('Top rated')],
            ['value' => 'price_low', 'label' => __('Price: low to high')],
            ['value' => 'price_high', 'label' => __('Price: high to low')],
            ['value' => 'newest', 'label' => __('Newest')],
        ],
        'teachers' => $teacherCards,
        'total' => $teachers->total(),
        'pagination' => $pagination,
        'error' => $errors->any() ? $errors->first() : null,
        'urls' => [
            'home' => route('home'),
            'subjects' => route('catalog.subjects.index'),
            'teachers' => route('teachers.index'),
            'contact' => route('legal.contact'),
            'login' => route('login'),
            'registerStudent' => route('register', ['role' => 'student']),
            'dashboard' => route('dashboard'),
            'privacy' => route('legal.privacy'),
            'terms' => route('legal.terms'),
            'refunds' => route('legal.refunds'),
        ],
        'support' => [
            'email' => config('studylikepro.support.email'),
            'phone' => config('studylikepro.support.phone'),
            'hours' => config('studylikepro.support.hours'),
        ],
        'images' => [
            'logo' => asset('images/brand/logo-mark.png'),
        ],
    ];

    // Pagination URLs carry query strings; JSON_HEX_AMP would turn their "&"
    // into a literal entity, so only the script-breaking characters are escaped.
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
@endphp

<x-public-page
    :title="__('Find a teacher')"
    :description="__('Browse verified Sri Lankan teachers by level, subject, lesson, grade, language, price and availability.')"
    :og-image="asset('images/hero/hero-students-1200.jpg')"
    :vite="['resources/css/app.css', 'resources/js/public/main.js']"
>
    <script type="application/json" id="public-teachers-config">@json($config, $jsonFlags)</script>

    <div id="public-teachers-app"></div>

    {{-- Fallback for crawlers and visitors without JavaScript. --}}
    <noscript>
        <div style="max-width:44rem;margin:0 auto;padding:3rem 1.5rem;font-family:system-ui,sans-serif;line-height:1.6">
            <h1>{{ __('Find your teacher') }}</h1>
            <p>{{ __('Every teacher here is verified by our team. Pick your level and subject to see the grades and lessons they teach.') }}</p>

            @if ($teachers->isEmpty())
                <p>{{ __('No verified teachers match those filters yet. Try widening your search.') }}</p>
            @else
                <ul>
                    @foreach ($teachers as $teacher)
                        <li>
                            <a href="{{ route('teachers.show', $teacher) }}">{{ $teacher->user->name }}</a>
                            @if ($teacher->headline) — {{ $teacher->headline }} @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            <p><a href="{{ route('catalog.subjects.index') }}">{{ __('Browse subjects') }}</a></p>
        </div>
    </noscript>
</x-public-page>
