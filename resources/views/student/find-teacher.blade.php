@php
    // The finder is rendered by resources/js/student-teacher-finder. Blade owns
    // the grade scope, the filter options, the money and timezone formatting,
    // the pre-formatted teacher cards and the route map, then hands them over as
    // a JSON island, so no URL, figure or translated string is hardcoded in the
    // Vue app.
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);
    $themePreset = auth()->user()->theme['preset'];

    $selectedSubject = $subjects->firstWhere('slug', $filters['subject'] ?? null);
    $gradeId = $grade?->id;
    $dateFilter = $filters['date'] ?? null;
    $today = now(config('studylikepro.default_display_timezone'))->toDateString();

    // The sort order alone is not a filter, so it never counts as one here.
    $hasActiveFilters = collect($filters)->except('sort')->filter()->isNotEmpty();

    $cards = collect($teachers->items())->map(function ($teacher) use ($money, $selectedSubject, $gradeId, $gradesById, $nextSlots, $dateFilter, $viewerTz) {
        $slot = $nextSlots[$teacher->id] ?? null;
        $ratingCount = (int) $teacher->rating_count;
        $lessonCount = (int) ($teacher->lessons_count ?? 0);

        $meta = array_values(array_filter([
            $teacher->gradeScopeLabel($gradesById),
            $lessonCount > 0 ? trans_choice(':count lesson|:count lessons', $lessonCount) : null,
        ]));

        return [
            'id' => $teacher->id,
            'url' => route('student.teachers.show', $teacher),
            'name' => $teacher->user->name,
            'initials' => mb_substr($teacher->user->name, 0, 2),
            'avatarUrl' => $teacher->user->avatarUrl(),
            'headline' => $teacher->headline,
            // A brand-new teacher has no rating yet: the card shows a "new"
            // chip instead of a meaningless zero.
            'rating' => $ratingCount > 0 ? number_format((float) $teacher->rating_avg, 1) : null,
            'ratingCount' => $ratingCount,
            'subjects' => $teacher->subjects->take(3)
                ->map(fn ($subject) => trim(($subject->icon ?? '').' '.$subject->name))
                ->values()->all(),
            'extraSubjects' => max(0, $teacher->subjects->count() - 3),
            'metaLabel' => $meta === [] ? null : implode(' · ', $meta),
            // Every lesson is priced at the student's own grade rate: the picked
            // subject when there is one, otherwise the cheapest subject.
            'rate' => $money($selectedSubject !== null
                ? $teacher->effectiveRateFor($selectedSubject, $gradeId)
                : $teacher->startingRateMinor($gradeId)),
            // The chosen day is already summed up in one sentence, so the card
            // prints the slot the teacher genuinely has open then.
            'availability' => $slot === null ? null : [
                'label' => $dateFilter !== null
                    ? __('Available :when', ['when' => $slot['starts_at']->setTimezone($viewerTz)->format('D d M · H:i')])
                    : __('Next: :when', ['when' => $slot['starts_at']->setTimezone($viewerTz)->format('D d M · H:i')]),
            ],
        ];
    })->values()->all();

    $finderPayload = [
        'appName' => config('app.name', 'Studylikepro'),
        'theme' => ['preset' => $themePreset],
        'grade' => $grade === null ? null : ['label' => $grade->label],
        'filters' => [
            'subject' => $filters['subject'] ?? '',
            'language' => $filters['language'] ?? '',
            'sort' => $sort,
            'date' => $dateFilter ?? '',
            'time_from' => $filters['time_from'] ?? '',
            'time_to' => $filters['time_to'] ?? '',
        ],
        // The picker only offers the subjects the student's grade actually runs.
        'subjectOptions' => $subjects
            ->map(fn ($subject) => [
                'value' => $subject->slug,
                'label' => trim(($subject->icon ?? '').' '.$subject->name),
            ])
            ->values()->all(),
        'languageOptions' => array_values(config('studylikepro.languages')),
        'sortOptions' => collect([
            'available_soon' => __('Earliest availability'),
            'rating' => __('Top rated'),
            'price_low' => __('Price: low to high'),
            'price_high' => __('Price: high to low'),
            'newest' => __('Newest'),
        ])->map(fn (string $label, string $value) => ['value' => $value, 'label' => $label])
            ->values()->all(),
        'today' => $today,
        'total' => $teachers->total(),
        'totalLabel' => trans_choice(':count teacher found|:count teachers found', $teachers->total()),
        'hasActiveFilters' => $hasActiveFilters,
        'hasDateFilter' => $dateFilter !== null,
        'errorMessage' => $errors->any() ? $errors->first() : null,
        'teachers' => $cards,
        'copy' => [
            'gradeTitle' => $grade === null ? '' : __('Teachers for :grade', ['grade' => $grade->label]),
            'gradeBody' => $grade === null ? '' : __('Every teacher below takes :grade lessons. Narrow the list by subject, language, or the day and time you want your lesson.', ['grade' => $grade->label]),
            'changeGrade' => __('Change grade'),
            'noGrade' => __('Add your grade to your learning profile and we will only show teachers who take it.'),
            'completeProfile' => __('Complete your profile'),
            'filtersLabel' => __('Filter teachers'),
            'subject' => __('Subject'),
            'anySubject' => __('Any subject'),
            'language' => __('Language'),
            'anyLanguage' => __('Any language'),
            'sortBy' => __('Sort by'),
            'date' => __('Date'),
            'anyDay' => __('Leave empty for any day'),
            'from' => __('From'),
            'to' => __('To'),
            'submit' => __('Show teachers'),
            'clear' => __('Clear filters'),
            'newTeacher' => __('New teacher'),
            'verified' => __('Verified'),
            'perHour' => __('/hr'),
            'viewProfile' => __('View profile'),
            'noSlots' => __('No open slots in the next 7 days'),
            'emptyTitle' => __('No teachers match yet'),
            'emptyWithDate' => __('Nobody is free on that day and time yet. Try another day or widen the time window.'),
            'emptyBody' => __('Try another subject or language, or clear the filters to see everyone who takes your grade.'),
        ],
        'urls' => [
            'index' => route('student.teachers.index'),
            'reset' => route('student.teachers.index'),
            'profile' => route('student.profile'),
        ],
    ];

    // JSON_UNESCAPED_SLASHES keeps the URLs readable and JSON_UNESCAPED_UNICODE
    // keeps the subject icons and translated labels legible; the HEX flags keep
    // the island safe inside a <script> tag.
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Find a teacher') }}</h2>
            @if ($grade)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">
                    🎓 {{ __('Matched to :grade', ['grade' => $grade->label]) }}
                </span>
            @endif
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl">
        <script type="application/json" id="student-teacher-finder-config">@json($finderPayload, $jsonFlags)</script>

        <div id="student-teacher-finder-app" class="space-y-6"></div>

        {{-- Fallback for a browser without JavaScript: the page is a thin shell,
             so repeat the filters as a plain GET form and list the same teachers
             the Vue app would have rendered. --}}
        <noscript>
            <div class="space-y-6">
                @if ($grade)
                    <div class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                        <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ __('Teachers for :grade', ['grade' => $grade->label]) }}</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Every teacher below takes :grade lessons.', ['grade' => $grade->label]) }}</p>
                    </div>
                @else
                    <div class="rounded-2xl border border-amber-200/80 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300">
                        {{ __('Add your grade to your learning profile and we will only show teachers who take it.') }}
                        <a href="{{ route('student.profile') }}" class="font-semibold underline">{{ __('Complete your profile') }}</a>
                    </div>
                @endif

                <form method="GET" action="{{ route('student.teachers.index') }}" class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        <label class="text-xs font-semibold uppercase tracking-wider text-slate-400">
                            {{ __('Subject') }}
                            <select name="subject" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                <option value="">{{ __('Any subject') }}</option>
                                @foreach ($subjects as $subject)
                                    <option value="{{ $subject->slug }}" @selected(($filters['subject'] ?? '') === $subject->slug)>{{ $subject->icon }} {{ $subject->name }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="text-xs font-semibold uppercase tracking-wider text-slate-400">
                            {{ __('Language') }}
                            <select name="language" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                <option value="">{{ __('Any language') }}</option>
                                @foreach (config('studylikepro.languages') as $language)
                                    <option value="{{ $language }}" @selected(($filters['language'] ?? '') === $language)>{{ $language }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="text-xs font-semibold uppercase tracking-wider text-slate-400">
                            {{ __('Sort by') }}
                            <select name="sort" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                <option value="available_soon" @selected($sort === 'available_soon')>{{ __('Earliest availability') }}</option>
                                <option value="rating" @selected($sort === 'rating')>{{ __('Top rated') }}</option>
                                <option value="price_low" @selected($sort === 'price_low')>{{ __('Price: low to high') }}</option>
                                <option value="price_high" @selected($sort === 'price_high')>{{ __('Price: high to low') }}</option>
                                <option value="newest" @selected($sort === 'newest')>{{ __('Newest') }}</option>
                            </select>
                        </label>

                        <label class="text-xs font-semibold uppercase tracking-wider text-slate-400">
                            {{ __('Date') }}
                            <input name="date" type="date" min="{{ $today }}" value="{{ $dateFilter }}" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                        </label>

                        <label class="text-xs font-semibold uppercase tracking-wider text-slate-400">
                            {{ __('From') }}
                            <input name="time_from" type="time" value="{{ $filters['time_from'] ?? '' }}" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                        </label>

                        <label class="text-xs font-semibold uppercase tracking-wider text-slate-400">
                            {{ __('To') }}
                            <input name="time_to" type="time" value="{{ $filters['time_to'] ?? '' }}" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                        </label>
                    </div>

                    <button type="submit" class="mt-4 rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white">{{ __('Show teachers') }}</button>
                </form>

                @forelse ($teachers as $teacher)
                    <a href="{{ route('student.teachers.show', $teacher) }}" class="block rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                        <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ $teacher->user->name }}</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $teacher->headline }}</p>
                        <p class="mt-2 text-sm font-semibold text-slate-800 dark:text-slate-100">
                            {{ $money($selectedSubject !== null ? $teacher->effectiveRateFor($selectedSubject, $gradeId) : $teacher->startingRateMinor($gradeId)) }}<span class="text-xs font-medium text-slate-400">/hr</span>
                        </p>
                    </a>
                @empty
                    <div class="rounded-2xl border border-slate-200/80 bg-white p-6 text-center dark:border-slate-800/80 dark:bg-slate-900">
                        <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ __('No teachers match yet') }}</h3>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Try another subject or language, or clear the filters to see everyone who takes your grade.') }}</p>
                    </div>
                @endforelse
            </div>
        </noscript>

        {{-- Laravel keeps pagination server-rendered: the links are URLs and
             markup, which is exactly what Blade still owns. --}}
        @if ($teachers->hasPages())
            <div class="mt-6">{{ $teachers->links() }}</div>
        @endif
    </div>

    @vite('resources/js/student-teacher-finder/main.js')
</x-app-layout>
