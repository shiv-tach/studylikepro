@php
    // /teacher/availability/setup is onboarding step 2 and is rendered by
    // resources/js/teacher-availability-setup. Blade owns the week it starts
    // from (the teacher's saved ranges, a replayed rejected save, or the
    // recommended preset for a brand-new teacher), the preset catalog, the
    // validation messages and the route map. Times are teacher-local clock
    // times, exactly like the stored `teacher_availability_slots` minutes.
    $themePreset = $userTheme['preset'] ?? 'classic';
    $weekdays = config('studylikepro.weekdays');
    $steps = \App\Support\TeacherOnboarding::steps($profile);
    $presets = \App\Support\AvailabilityPresets::all();
    $lessonDuration = $profile->lessonDuration();
    $timezone = $profile->timezone ?: config('studylikepro.default_display_timezone');

    $savedWeek = collect($weekdays)
        ->map(fn (string $label, int $day) => [
            'day' => $day,
            'ranges' => $profile->availabilitySlots
                ->where('day_of_week', $day)
                ->map(fn ($slot) => ['start' => $slot->startLabel(), 'end' => $slot->endLabel()])
                ->values()
                ->all(),
        ])
        ->values();

    // A rejected save wins over the stored ranges so the teacher can see and fix
    // exactly what they submitted.
    $oldDays = old('days');
    $sourceWeek = $savedWeek;

    if (is_array($oldDays)) {
        $sourceWeek = collect(array_values($oldDays))->map(fn (array $day) => [
            'day' => (int) ($day['value'] ?? 0),
            'ranges' => collect($day['ranges'] ?? [])
                ->map(fn (array $range) => [
                    'start' => (string) ($range['start_time'] ?? ''),
                    'end' => (string) ($range['end_time'] ?? ''),
                ])
                ->values()
                ->all(),
        ]);
    } elseif ($profile->availabilitySlots->isEmpty()) {
        // Brand-new teacher: open on the recommended week, unsaved, so the step
        // is one review away from done — and one click away from a clean slate.
        $recommended = \App\Support\AvailabilityPresets::recommended();

        $sourceWeek = collect($recommended['days'])->map(fn (array $ranges, int $day) => [
            'day' => $day,
            'ranges' => $ranges,
        ]);
    }

    $byDay = $sourceWeek->keyBy('day');

    $week = collect($weekdays)
        ->map(function (string $label, int $day) use ($byDay) {
            $entry = $byDay->get($day, ['ranges' => []]);

            return [
                'value' => $day,
                'label' => $label,
                'shortLabel' => substr($label, 0, 3),
                'enabled' => count($entry['ranges']) > 0,
                'ranges' => array_values($entry['ranges']),
            ];
        })
        ->values()
        ->all();

    // Laravel keys its messages positionally, so the island carries them under
    // the same names the rows submit. The Vue editor only has to read them.
    $rowErrors = [];

    foreach (array_keys($week) as $index) {
        $rowErrors["days.$index.ranges"] = $errors->get("days.$index.ranges");

        foreach (range(0, 3) as $row) {
            $rowErrors["days.$index.ranges.$row.start_time"] = $errors->get("days.$index.ranges.$row.start_time");
            $rowErrors["days.$index.ranges.$row.end_time"] = $errors->get("days.$index.ranges.$row.end_time");
        }
    }

    $payload = [
        'appName' => config('app.name', 'Studylikepro'),
        'csrfToken' => csrf_token(),
        'theme' => ['preset' => $themePreset],
        'status' => [
            'profileCompleted' => session('status') === 'profile-completed',
        ],
        'profile' => [
            'isComplete' => $profile->isComplete(),
            'hasAvailability' => $profile->availabilitySlots->isNotEmpty(),
            'lessonDuration' => $lessonDuration,
            'timezone' => $timezone,
        ],
        'week' => $week,
        'presets' => $presets,
        'limits' => [
            'maxRangesPerDay' => 4,
        ],
        'errors' => $rowErrors,
        'errorMessages' => $errors->all(),
        'steps' => $steps,
        'currentStep' => 2,
        'urls' => [
            'update' => route('teacher.availability.setup.update'),
            'verification' => route('teacher.verification'),
            'profile' => route('teacher.profile'),
            'availability' => route('teacher.availability.index'),
        ],
        'copy' => [
            'profileCompletedTitle' => __('Step 1 complete'),
            'profileCompletedBody' => __('Your teaching profile is saved. Set the days and hours you teach so students can book you.'),
            'errorTitle' => __('Please fix the following:'),
            'stepsTitle' => __('Teacher setup'),
            'stepsCompleted' => __(':completed of :total steps completed'),
            'stepLabel' => __('Step :number'),
            'stepStatusDone' => __('Completed'),
            'stepStatusCurrent' => __('In progress'),
            'stepStatusUpNext' => __('Up next'),
            'stepStatusLocked' => __('Locked'),
            'presetTitle' => __('Suggested weeks'),
            'presetHint' => __('Load a full week, then change anything you like. Nothing is saved until you press save.'),
            'presetRecommended' => __('Recommended'),
            'presetApply' => __('Use this week'),
            'presetApplied' => __('Week loaded into the editor — review the days below.'),
            'clearWeek' => __('Clear week'),
            'editorTitle' => __('Your week'),
            'editorHint' => __('Students can book lessons inside these hours.'),
            'timezoneNote' => __('Times are in :timezone.', ['timezone' => $timezone]),
            'lessonLengthNote' => __('Your lessons are :minutes minutes long, so every range must last at least that long.', ['minutes' => $lessonDuration]),
            'dayAvailable' => __('Available'),
            'dayUnavailable' => __('Not available'),
            // The placeholder stays for the Vue editor to fill per day.
            'dayToggle' => __('Toggle :day availability'),
            'fromLabel' => __('From'),
            'toLabel' => __('To'),
            'addRange' => __('Add time range'),
            'removeRange' => __('Remove this range'),
            'rangesHint' => __('Split a day into more than one block if you teach in the morning and the evening.'),
            'noRanges' => __('Add at least one range'),
            'summaryTitle' => __('Weekly summary'),
            'daysCountLabel' => __('Teaching days'),
            'hoursLabel' => __('Weekly hours'),
            'lessonsLabel' => __('Lesson slots'),
            'daysCount' => __(':count days a week'),
            'weeklyHours' => __(':hours hours a week'),
            'lessonsPreview' => __('About :count lessons a week'),
            'emptySubmit' => __('Add at least one complete range before saving.'),
            'unsaved' => __('You have unsaved changes.'),
            'saved' => __('All changes saved.'),
            'saving' => __('Saving…'),
            'save' => __('Save availability & continue to verification'),
            'undo' => __('Undo changes'),
            'confirmPresetTitle' => __('Replace the week?'),
            'confirmPresetBody' => __('Loading this preset replaces the times currently in the editor.'),
            'confirmPresetConfirm' => __('Load preset'),
            'confirmClearTitle' => __('Clear the whole week?'),
            'confirmClearBody' => __('Every time range in the editor is removed. Nothing is saved until you press save.'),
            'confirmClearConfirm' => __('Clear week'),
            'confirmCancel' => __('Cancel'),
            'confirmClose' => __('Close'),
        ],
    ];

    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;

    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $fieldClasses = 'rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Set your weekly availability') }}</h2>
            <span class="inline-flex items-center rounded-full bg-primary/10 px-2.5 py-0.5 text-[11px] font-semibold text-primary">{{ $timezone }}</span>
        </div>
    </x-slot>

    <script type="application/json" id="teacher-availability-setup-config">@json($payload, $jsonFlags)</script>

    <div id="teacher-availability-setup-app"></div>

    {{-- No JavaScript: the same PUT request, one range per day. Presets are a
         convenience of the Vue editor, so this twin is the manual form. --}}
    <noscript>
        <div class="mx-auto max-w-3xl space-y-6">
            @if (session('status') === 'profile-completed')
                <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                    {{ __('Your teaching profile is saved. Set the days and hours you teach so students can book you.') }}
                </div>
            @endif

            <x-teacher-onboarding-steps :current="2" />

            <form method="POST" action="{{ route('teacher.availability.setup.update') }}" class="space-y-6">
                @csrf
                @method('PUT')

                <div class="{{ $cardBase }} {{ $cardTheme }}">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Your week') }}</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                        {{ __('Times are in :timezone.', ['timezone' => $timezone]) }}
                        {{ __('Your lessons are :minutes minutes long, so every range must last at least that long.', ['minutes' => $lessonDuration]) }}
                    </p>

                    <x-input-error :messages="$errors->get('days')" class="mt-3" />

                    <div class="mt-5 space-y-4">
                        @foreach ($week as $index => $day)
                            <fieldset class="rounded-xl border border-slate-200/80 p-3 dark:border-slate-800">
                                <input type="hidden" name="days[{{ $index }}][value]" value="{{ $day['value'] }}">

                                <label class="flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-200">
                                    <input type="checkbox" name="days[{{ $index }}][enabled]" value="1"
                                           class="rounded border-slate-300 text-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900"
                                           @checked($day['enabled']) />
                                    {{ $day['label'] }}
                                </label>

                                <div class="mt-3 flex flex-wrap items-end gap-3">
                                    <div>
                                        <x-input-label for="day-{{ $index }}-start" :value="__('From')" />
                                        <input id="day-{{ $index }}-start" name="days[{{ $index }}][ranges][0][start_time]" type="time"
                                               value="{{ $day['ranges'][0]['start'] ?? '' }}" class="mt-1 {{ $fieldClasses }}" />
                                    </div>
                                    <div>
                                        <x-input-label for="day-{{ $index }}-end" :value="__('To')" />
                                        <input id="day-{{ $index }}-end" name="days[{{ $index }}][ranges][0][end_time]" type="time"
                                               value="{{ $day['ranges'][0]['end'] ?? '' }}" class="mt-1 {{ $fieldClasses }}" />
                                    </div>
                                </div>

                                <x-input-error :messages="$errors->get('days.'.$index.'.ranges')" class="mt-2" />
                                <x-input-error :messages="$errors->get('days.'.$index.'.ranges.0.start_time')" class="mt-2" />
                                <x-input-error :messages="$errors->get('days.'.$index.'.ranges.0.end_time')" class="mt-2" />
                            </fieldset>
                        @endforeach
                    </div>

                    <div class="mt-5 flex justify-end">
                        <x-primary-button>{{ __('Save availability & continue to verification') }}</x-primary-button>
                    </div>
                </div>
            </form>
        </div>
    </noscript>

    @vite('resources/js/teacher-availability-setup/main.js')
</x-app-layout>
