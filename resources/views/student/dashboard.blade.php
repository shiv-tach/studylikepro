@php
    // The dashboard is rendered by resources/js/student-dashboard. Blade owns the
    // copy, the route map and the student's live numbers, then hands them over as
    // a JSON island, so no URL or figure is ever hardcoded in the Vue app.
    $quickActions = [
        [
            'key' => 'lessons',
            'urlKey' => 'bookings',
            'eyebrow' => __('Lessons'),
            'label' => __('My lessons'),
            'description' => __('Holds, upcoming lessons and past history.'),
            'icon' => 'calendar',
            'tone' => 'emerald',
            'badge' => $upcomingLessonCount > 0 ? __(':count upcoming', ['count' => $upcomingLessonCount]) : __('None booked'),
        ],
        [
            'key' => 'profile',
            'urlKey' => 'profile',
            'eyebrow' => __('Profile'),
            'label' => __('My learning profile'),
            'description' => __('Grade, goals and photo.'),
            'icon' => 'user',
            'tone' => 'primary',
            'badge' => null,
        ],
        [
            'key' => 'theme',
            'urlKey' => 'theme',
            'eyebrow' => __('Appearance'),
            'label' => __('Theme & appearance'),
            'description' => __('Personalize presets, accents, and dark mode.'),
            'icon' => 'palette',
            'tone' => 'violet',
            'badge' => null,
        ],
        [
            'key' => 'subjects',
            'urlKey' => 'subjects',
            'eyebrow' => __('Learning'),
            'label' => __('My subjects'),
            'description' => __('Your compulsory and picked subjects — open one to explore lessons, teachers and services.'),
            'icon' => 'book',
            'tone' => 'violet',
            'badge' => $subjectCount > 0 ? $subjectCount.' '.Str::plural('subject', $subjectCount) : __('Browse subjects'),
        ],
        [
            'key' => 'teachers',
            'urlKey' => 'teachers',
            'eyebrow' => __('Tutors'),
            'label' => __('Find a teacher'),
            'description' => __('Verified tutors who take your grade — filter by subject, language and lesson times.'),
            'icon' => 'users',
            'tone' => 'primary',
            'badge' => $gradeLabel ?? __('Directory'),
        ],
    ];

    $dashboard = [
        'appName' => config('app.name', 'Studylikepro'),
        'user' => [
            'name' => Auth::user()->name,
            'grade' => $gradeLabel,
            'timezone' => $displayTimezone,
            'learningLanguage' => $learningLanguage,
        ],
        'theme' => ['preset' => $themePreset],
        'flash' => ['profileCompleted' => session('status') === 'profile-completed'],
        'stats' => [
            'subjects' => $subjectCount,
            'upcomingLessons' => $upcomingLessonCount,
            'completedLessons' => $completedLessonCount,
            'reviewedLessons' => $reviewedLessonCount,
        ],
        'nextLesson' => $nextLesson,
        'awaitingReview' => $awaitingReview,
        'quickActions' => $quickActions,
        'urls' => [
            'bookings' => route('student.bookings.index'),
            'pastBookings' => route('student.bookings.index', ['tab' => 'past']),
            'profile' => route('student.profile'),
            'theme' => route('settings.theme'),
            'subjects' => route('student.interests.edit'),
            'teachers' => route('student.teachers.index'),
        ],
    ];

    // JSON_UNESCAPED_SLASHES keeps the URLs readable; the HEX flags keep the
    // island safe inside a <script> tag.
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <span class="hidden h-9 w-9 items-center justify-center rounded-xl bg-primary/10 p-2 text-primary sm:flex">
                <svg class="h-full w-full" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M12 4.5l8.5 3.8-8.5 3.8-8.5-3.8z" />
                    <path d="M7 10.6V15c0 1.7 2.2 3 5 3s5-1.3 5-3v-4.4" />
                    <path d="M19.5 9.2V14" />
                </svg>
            </span>
            <div>
                <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">
                    {{ __('My Learning') }}
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ __('Every lesson and next step in one place.') }}
                </p>
            </div>
        </div>
    </x-slot>

    <script type="application/json" id="student-dashboard-config">@json($dashboard, $jsonFlags)</script>

    <div id="student-dashboard-app" class="space-y-6"></div>

    {{-- Fallback for a browser without JavaScript: the page is a thin shell, so
         point students straight at the destinations the dashboard links to. --}}
    <noscript>
        <div class="mt-6 rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
            <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ __('Your learning') }}</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ __('The dashboard needs JavaScript. Your main destinations are:') }}
            </p>
            <ul class="mt-4 space-y-2 text-sm font-semibold text-primary">
                <li><a class="hover:underline" href="{{ route('student.bookings.index') }}">{{ __('My lessons') }}</a></li>
                <li><a class="hover:underline" href="{{ route('student.interests.edit') }}">{{ __('My subjects') }}</a></li>
                <li><a class="hover:underline" href="{{ route('student.teachers.index') }}">{{ __('Find a teacher') }}</a></li>
                <li><a class="hover:underline" href="{{ route('student.profile') }}">{{ __('My learning profile') }}</a></li>
                <li><a class="hover:underline" href="{{ route('settings.theme') }}">{{ __('Theme & appearance') }}</a></li>
            </ul>
        </div>
    </noscript>

    @vite('resources/js/student-dashboard/main.js')
</x-app-layout>
