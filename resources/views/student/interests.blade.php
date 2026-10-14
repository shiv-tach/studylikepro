@php
    // The "My subjects" page is rendered by resources/js/student-subjects. Blade
    // owns the copy, the subject groups and the route map, then hands them over
    // as a JSON island, so no URL or string is hardcoded in the Vue app.
    $themePreset = $userTheme['preset'] ?? 'classic';

    $groups = $subjectGroups->map(function (array $group) use ($baskets, $grade) {
        $basket = $group['basket'];

        return [
            'key' => $basket?->key ?? 'compulsory',
            // Below the basket grades the page stays a flat grid: no heading,
            // no "chosen"/"all students" badge.
            'title' => $baskets->isEmpty() ? null : ($basket === null
                ? __('Compulsory subjects')
                : trim(($basket->icon ?? '').' '.$basket->name)),
            'badge' => $baskets->isEmpty() ? null : ($basket === null ? __('all students') : __('chosen')),
            'badgeTone' => $basket === null ? 'slate' : 'primary',
            'description' => $basket?->description,
            'subjectsLabel' => trans_choice(':count subject|:count subjects', $group['subjects']->count()),
            'subjects' => $group['subjects']->map(fn ($subject) => [
                'id' => $subject->id,
                'name' => $subject->name,
                'icon' => $subject->icon ?? '📘',
                'description' => $subject->description,
                'lessonLabel' => $subject->lessons->count().' '.Str::plural('lesson', $subject->lessons->count()),
                'url' => route('student.subjects.show', array_filter([
                    'subject' => $subject->slug,
                    'grade' => $grade?->id,
                ])),
            ])->values()->all(),
        ];
    })->values();

    // Summary figures for the hero: every subject on the page and the number of
    // distinct subject areas (the compulsory block plus each picked basket).
    $subjectsCount = $groups->sum(fn (array $group) => count($group['subjects']));
    $areasCount = $groups->filter(fn (array $group) => $group['title'] !== null)->count();

    $reminder = session('basketReminder');

    $interests = [
        'appName' => config('app.name', 'Studylikepro'),
        'theme' => ['preset' => $themePreset],
        'gradeLabel' => $grade?->label,
        'hasBaskets' => $baskets->isNotEmpty(),
        'groups' => $groups,
        'stats' => [
            'subjects' => $subjectsCount,
            'subjectsLabel' => trans_choice(':count subject|:count subjects', $subjectsCount),
            'areas' => $areasCount,
            'areasLabel' => trans_choice(':count subject area|:count subject areas', $areasCount),
        ],
        'flash' => [
            'updated' => session('status') === 'interests-updated',
            'basketReminder' => is_array($reminder) && $reminder !== []
                ? __('You have not picked a subject from :baskets yet — your teacher can help you choose.', ['baskets' => implode(', ', $reminder)])
                : null,
        ],
        'copy' => [
            'heroTitle' => __('The subjects you are focusing on'),
            'heroIntro' => match (true) {
                $grade === null => __('Open a subject to see its lessons, teachers and services.'),
                $baskets->isNotEmpty() => __('Every compulsory :grade subject plus the category subjects you picked. Open any subject to see its lessons, teachers and services.', ['grade' => $grade->label]),
                default => __('All the :grade subjects you are studying. Open one to see its lessons, teachers and services.', ['grade' => $grade->label]),
            },
            'empty' => __('Subjects for your grade are being prepared — check back soon!'),
            'viewCta' => __('View lessons and teachers'),
            'updated' => __('Your subjects are saved.'),
            'findTeacher' => __('Find a teacher'),
            'backToDashboard' => __('Back to my dashboard'),
        ],
        'urls' => [
            'teachers' => route('student.teachers.index'),
            'dashboard' => route('student.dashboard'),
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
                    <path d="M5 4.5h8.5a3 3 0 013 3v12H8a3 3 0 01-3-3z" />
                    <path d="M16.5 19.5H18a1 1 0 001-1V7" />
                </svg>
            </span>
            <div>
                <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">
                    {{ __('My subjects') }}
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ __('What you are studying right now.') }}
                </p>
            </div>
        </div>
    </x-slot>

    <script type="application/json" id="student-interests-config">@json($interests, $jsonFlags)</script>

    <div id="student-interests-app"></div>

    {{-- Fallback for a browser without JavaScript: the page is a thin shell, so
         list the same subjects the Vue app would have rendered. --}}
    <noscript>
        <div class="mx-auto max-w-4xl space-y-6">
            @foreach ($subjectGroups as $group)
                <div class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">
                        {{ $group['basket'] === null ? __('Your subjects') : trim(($group['basket']->icon ?? '').' '.$group['basket']->name) }}
                    </h3>
                    <ul class="mt-3 space-y-2 text-sm font-semibold text-primary">
                        @foreach ($group['subjects'] as $subject)
                            <li>
                                <a class="hover:underline" href="{{ route('student.subjects.show', array_filter(['subject' => $subject->slug, 'grade' => $grade?->id])) }}">
                                    {{ $subject->icon ?? '📘' }} {{ $subject->name }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach

            <p class="text-sm text-slate-500 dark:text-slate-400">
                <a class="font-semibold text-primary hover:underline" href="{{ route('student.dashboard') }}">{{ __('Back to my dashboard') }}</a>
            </p>
        </div>
    </noscript>

    @vite('resources/js/student-subjects/main.js')
</x-app-layout>
