@php
    // The dedicated teacher page is rendered by resources/js/student-teacher-profile.
    // Blade keeps ownership of the grade scope, the prices, the permission checks
    // and the route map, then hands them over as a JSON island.
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);
    $themePreset = $userTheme['preset'] ?? 'classic';

    // Everything on this page is priced and scoped for the student's grade.
    $scopeGradeId = $gradeScoped ? $studentGrade->id : null;
    $ratingCount = (int) $teacher->rating_count;
    $ratingAvg = $ratingCount > 0 ? number_format((float) $teacher->rating_avg, 1) : null;

    // Slot groups, each already labelled in the viewer's timezone. Only the
    // teacher's nearest open days arrive here, so the panel stays a day rail:
    // pick a day, then read its times.
    $today = \Carbon\CarbonImmutable::now($viewerTz);
    $todayDate = $today->toDateString();
    $tomorrowDate = $today->addDay()->toDateString();

    $slotsByDate = [];
    foreach ($availability as $slot) {
        $local = $slot['starts_at']->setTimezone($viewerTz);
        $slotsByDate[$local->toDateString()][] = $local;
    }

    // The panel is a quick glance, not a calendar: keep the same nearest-open-
    // days window the booking page's picker uses.
    $quickPickDays = (int) config('studylikepro.booking.quick_pick_days');
    $slotsByDate = $quickPickDays > 0 ? array_slice($slotsByDate, 0, $quickPickDays, true) : $slotsByDate;

    $availabilityDays = [];
    foreach ($slotsByDate as $date => $slots) {
        $day = \Carbon\CarbonImmutable::parse($date, $viewerTz);

        $availabilityDays[] = [
            'date' => (string) $date,
            'label' => $day->format('D, d M'),
            'weekday' => $day->format('D'),
            'dayNumber' => $day->format('j'),
            'month' => $day->format('M'),
            'fullLabel' => $day->format('l, j F'),
            'isToday' => (string) $date === $todayDate,
            'isTomorrow' => (string) $date === $tomorrowDate,
            'slotCountLabel' => trans_choice(':count slot|:count slots', count($slots)),
            'slots' => array_map(fn ($slot) => [
                'value' => $slot->toIso8601String(),
                'label' => display_time($slot),
            ], $slots),
        ];
    }

    // The subjects this teacher takes at the student's grade, each with the
    // lessons that grade runs and the rate that grade pays.
    $subjectCards = $gradeScoped ? $teacher->subjects->map(function ($subject) use ($teacher, $lessonsBySubject, $money, $scopeGradeId, $studentGrade) {
        return [
            'id' => $subject->id,
            'icon' => $subject->icon ?? '📘',
            'name' => $subject->name,
            'level' => $subject->educationLevel?->name,
            'rate' => $money($teacher->effectiveRateFor($subject, $scopeGradeId)),
            'gradeLabel' => $studentGrade->label,
            'lessons' => $lessonsBySubject->get($subject->id, collect())->pluck('name')->values()->all(),
        ];
    })->values()->all() : [];

    // Every service row is pre-formatted, so a card never has to know how a
    // service type describes itself or where its action posts to.
    $serviceGroups = $offerings->map(function ($items) use ($money) {
        $type = $items->first()->type();

        return [
            'key' => $type->value,
            'icon' => $type->icon(),
            'label' => $type->label(),
            'items' => $items->map(function ($offering) use ($type, $money) {
                $gradeLabel = $offering->grade?->label ?? $offering->lesson?->grade?->label;

                $meta = match (true) {
                    $type === \App\Enums\ServiceType::Lesson && $offering->allocation_minutes
                        => __(':minutes minutes, split into sessions', ['minutes' => $offering->allocation_minutes]),
                    $type === \App\Enums\ServiceType::PaperMarking
                        => __('per paper · back within :hours h', ['hours' => $offering->turnaround_hours ?? config('studylikepro.services.marking_default_turnaround_hours')]),
                    (bool) $offering->default_session_minutes
                        => __(':minutes-minute live session', ['minutes' => $offering->default_session_minutes]),
                    default => null,
                };

                return [
                    'id' => $offering->id,
                    'title' => $offering->lesson?->name ?? $offering->subject?->name ?? $offering->title,
                    'metaLabel' => collect([
                        $gradeLabel,
                        $meta,
                    ])->filter()->implode(' · ') ?: null,
                    'price' => $money($offering->price_minor),
                    // Buying a paper service starts a marking order; every other
                    // service goes straight to checkout. The variant picks the
                    // button the card renders for it.
                    'action' => $type === \App\Enums\ServiceType::PaperMarking
                        ? ['kind' => 'link', 'variant' => 'outline', 'label' => __('Submit paper'), 'url' => route('student.marking.create', $offering)]
                        : ['kind' => 'form', 'variant' => 'primary', 'label' => __('Buy'), 'url' => route('student.purchases.store', $offering)],
                ];
            })->values()->all(),
        ];
    })->values()->all();

    $totalReviews = array_sum($breakdown);

    $reviewCards = $reviews->map(fn ($review) => [
        'id' => $review->id,
        'reviewer' => $review->reviewerName(),
        'rating' => (int) $review->rating,
        'stars' => [
            'filled' => str_repeat('★', (int) $review->rating),
            'empty' => str_repeat('★', 5 - (int) $review->rating),
        ],
        'comment' => $review->comment,
        'meta' => collect([
            $review->booking?->lesson?->name ?? $review->booking?->subject?->name ?? __('Lesson'),
            $review->created_at->format('M Y'),
            $review->wasEdited() ? __('edited') : null,
        ])->filter()->implode(' · '),
    ])->values()->all();

    $canBook = auth()->user()->can('create', [\App\Models\Booking::class, $teacher]);
    $reviewsLabel = trans_choice(':count review|:count reviews', $ratingCount);

    $teacherPayload = [
        'appName' => config('app.name', 'Studylikepro'),
        'csrfToken' => csrf_token(),
        'theme' => ['preset' => $themePreset],
        'grade' => $studentGrade === null ? null : ['label' => $studentGrade->label],
        'gradeScoped' => $gradeScoped,
        'teacher' => [
            'name' => $teacher->user->name,
            'initials' => (string) Str::of($teacher->user->name)->substr(0, 2),
            'avatarUrl' => $teacher->user->avatarUrl(),
            'headline' => $teacher->headline,
            'bio' => $teacher->bio,
            'rating' => $ratingAvg,
            'ratingCount' => $ratingCount,
            'reviewsLabel' => $reviewsLabel,
            'lessonsTaught' => $teacher->lessons_completed_count.' '.__('lessons taught'),
            'experience' => $teacher->experience_years.' '.__('yrs experience'),
            'rateChip' => $scopeGradeId === null ? null : $money($teacher->startingRateMinor($scopeGradeId)).'/hr',
            'facts' => [
                ['label' => __('Education'), 'value' => $teacher->education],
                ['label' => __('Languages'), 'value' => implode(', ', $teacher->languages ?? [])],
                ['label' => __('Lesson length'), 'value' => $teacher->lessonDuration().' '.__('minutes')],
            ],
        ],
        'subjects' => $subjectCards,
        'serviceGroups' => $serviceGroups,
        'availability' => [
            'timezone' => $viewerTz,
            'timezoneLabel' => __('Your time (:timezone)', ['timezone' => $viewerTz]),
            'empty' => $availabilityDays === [],
            'days' => $availabilityDays,
            'windowNotice' => __('Your nearest open days — pick a day to see its times.'),
            'timezoneNotice' => __('All times are in :timezone.', ['timezone' => $viewerTz]),
        ],
        'reviews' => [
            'count' => $ratingCount,
            'rating' => $ratingAvg,
            'summaryLabel' => $reviewsLabel,
            'breakdown' => collect($breakdown)->map(fn ($count, $star) => [
                'label' => $star.' ★',
                'count' => $count,
                'percent' => $totalReviews > 0 ? (int) round($count / $totalReviews * 100) : 0,
            ])->values()->all(),
            'items' => $reviewCards,
            'showingLabel' => $ratingCount > $reviews->count()
                ? __('Showing the :count most recent reviews.', ['count' => $reviews->count()])
                : null,
        ],
        // The sidebar reads what Blade already decided: whether the student may
        // book this teacher, and what to offer them when they may not.
        'booking' => [
            'canBook' => $canBook,
            'scopeLabel' => $gradeScoped
                ? __('Starting at · :grade', ['grade' => $studentGrade->label])
                : __('Not for your grade'),
            'rate' => $scopeGradeId === null ? null : $money($teacher->startingRateMinor($scopeGradeId)),
            'perHour' => __('/hour'),
            'lessonLength' => $gradeScoped
                ? __(':minutes-minute live 1-on-1 lesson.', ['minutes' => $teacher->lessonDuration()])
                : null,
            'notice' => $gradeScoped
                ? null
                : __('This teacher does not take your grade right now. Find a verified teacher who does.'),
            'bookUrl' => $canBook ? route('student.bookings.create', $teacher) : null,
            'fallbackUrl' => $gradeScoped ? null : route('student.teachers.index'),
        ],
        // The trail the page top renders: a student can always step back up the
        // tree (Dashboard → Find a teacher → this teacher).
        'breadcrumbs' => [
            ['label' => __('Dashboard'), 'url' => route('student.dashboard')],
            ['label' => __('Find a teacher'), 'url' => route('student.teachers.index')],
            ['label' => $teacher->user->name, 'url' => null],
        ],
        'copy' => [
            'verified' => __('Verified'),
            'noReviewsYet' => __('No reviews yet'),
            'addGrade' => __('Add your grade to your learning profile to see only the lessons and rates that match you.'),
            'completeProfile' => __('Complete your profile'),
            'gradeMismatch' => __(':teacher does not teach :grade lessons yet.', [
                'teacher' => $teacher->user->name,
                'grade' => $studentGrade?->label,
            ]),
            'findGradeTeachers' => __('Find :grade teachers', ['grade' => $studentGrade?->label]),
            'subjectsTitle' => __('Subjects & lessons'),
            'subjectsEmpty' => __('This teacher is still setting up their subjects.'),
            'perHourShort' => __('/hr'),
            'servicesTitle' => __('Services & pricing'),
            'servicesTagline' => __('prices set by the teacher'),
            'availabilityTitle' => __('Availability'),
            'noSlots' => __('No open slots in the next two weeks — check back soon.'),
            'today' => __('Today'),
            'tomorrow' => __('Tomorrow'),
            'dayPickerLabel' => __('Choose a day'),
            'nextFree' => __('Next free'),
            'reviewsTitle' => __('Student reviews'),
            'noReviews' => __('No reviews yet — this teacher is new to Studylikepro.'),
            'outOfFive' => __(':rating out of 5'),
            'bookSlot' => __('Book a slot'),
            'pickSlotHint' => __('Pick one of the open slots above to book a live lesson.'),
            'findTeacher' => __('Find a teacher'),
        ],
        'urls' => [
            'index' => route('student.teachers.index'),
            'profile' => route('student.profile'),
        ],
    ];

    // JSON_UNESCAPED_SLASHES keeps the URLs readable; the HEX flags keep the
    // island safe inside a <script> tag.
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ $teacher->user->name }}</h2>
            @if ($gradeScoped)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">
                    🎓 {{ __(':grade lessons & rates', ['grade' => $studentGrade->label]) }}
                </span>
            @endif
        </div>
    </x-slot>

    <script type="application/json" id="student-teacher-profile-config">@json($teacherPayload, $jsonFlags)</script>

    <div id="student-teacher-profile-app"></div>

    {{-- Fallback for a browser without JavaScript: the page is a thin shell, so
         print the same teacher the Vue app would have rendered. --}}
    <noscript>
        <div class="w-full space-y-6">
            <div class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ $teacher->user->name }}</h3>
                @if ($teacher->headline)
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $teacher->headline }}</p>
                @endif

                @if ($scopeGradeId !== null)
                    <p class="mt-3 text-sm font-semibold text-slate-700 dark:text-slate-200">
                        {{ __('Starting at · :grade', ['grade' => $studentGrade->label]) }}
                        {{ $money($teacher->startingRateMinor($scopeGradeId)) }}{{ __('/hour') }}
                    </p>
                @else
                    <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                        {{ __('This teacher does not take your grade right now. Find a verified teacher who does.') }}
                    </p>
                @endif

                @if ($subjectCards !== [])
                    <ul class="mt-3 space-y-1 text-sm font-semibold text-slate-700 dark:text-slate-200">
                        @foreach ($subjectCards as $subject)
                            <li>• {{ $subject['icon'] }} {{ $subject['name'] }} — {{ $subject['rate'] }}{{ __('/hr') }}</li>
                        @endforeach
                    </ul>
                @endif

                <p class="mt-4 text-sm">
                    @can('create', [App\Models\Booking::class, $teacher])
                        <a class="font-semibold text-primary hover:underline" href="{{ route('student.bookings.create', $teacher) }}">{{ __('Book a slot') }}</a>
                        <span class="mx-2 text-slate-300">·</span>
                    @endcan
                    <a class="font-semibold text-primary hover:underline" href="{{ route('student.teachers.index') }}">{{ __('Find a teacher') }}</a>
                    <span class="mx-2 text-slate-300">·</span>
                    <a class="font-semibold text-primary hover:underline" href="{{ route('student.dashboard') }}">{{ __('Dashboard') }}</a>
                </p>
            </div>
        </div>
    </noscript>

    @vite('resources/js/student-teacher-profile/main.js')
</x-app-layout>
