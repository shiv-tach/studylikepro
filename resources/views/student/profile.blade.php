@php
    // /student/profile is rendered by resources/js/student-profile. Blade keeps
    // the catalog (levels → grades), the marketplace languages, the student's
    // own answers, the validation errors and the route map, then hands them over
    // as a JSON island, so no label or URL is hardcoded in the Vue app.
    $user = Auth::user();
    $themePreset = $userTheme['preset'] ?? 'classic';

    // Same flags the onboarding wizard shows for the medium of instruction.
    $languageIcons = ['Sinhala' => '🇱🇰', 'English' => '🌐'];

    // The limits StudentProfileRequest validates, so the counters and the
    // maxlength attributes never disagree with the server.
    $limits = ['learningGoals' => 1000, 'guardianName' => 120, 'guardianPhone' => 32];

    // The same level → grade tree the onboarding wizard walks, so this picker
    // can never offer a grade the wizard would not have offered.
    $gradeOptions = $levels->map(fn ($level) => [
        'id' => $level->id,
        'name' => $level->name,
        'icon' => $level->icon ?: '🎓',
        'grades' => $level->grades->map(fn ($grade) => [
            'id' => $grade->id,
            'label' => $grade->label,
        ])->values()->all(),
    ])->values()->all();

    // A rejected save comes back through old(), so the form always reopens with
    // the answers the student just submitted rather than the stored ones.
    $selectedGrade = old('grade_id', $profile?->grade_id);
    $selectedGrade = $selectedGrade === null || $selectedGrade === '' ? null : (int) $selectedGrade;

    $profilePayload = [
        'appName' => config('app.name', 'Studylikepro'),
        'csrfToken' => csrf_token(),
        'theme' => ['preset' => $themePreset],
        'student' => [
            'name' => $user->name,
            'email' => $user->email,
            'initials' => Str::upper(Str::substr($user->name, 0, 2)),
            'avatarUrl' => $user->avatarUrl(),
            'memberSince' => $user->created_at?->format('M Y'),
        ],
        'levels' => $gradeOptions,
        'languages' => collect(config('studylikepro.student_learning_languages'))->map(fn ($language) => [
            'value' => $language,
            'icon' => $languageIcons[$language] ?? '🗣️',
        ])->values()->all(),
        'limits' => $limits,
        'values' => [
            'gradeId' => $selectedGrade,
            'learningLanguage' => old('learning_language', $profile?->learning_language) ?? '',
            'learningGoals' => old('learning_goals', $profile?->learning_goals) ?? '',
            'guardianName' => old('guardian_name', $profile?->guardian_name) ?? '',
            'guardianPhone' => old('guardian_phone', $profile?->guardian_phone) ?? '',
        ],
        // Laravel owns the wording of every message; the app only decides where
        // it is shown, so the two never drift apart.
        'errors' => [
            'gradeId' => $errors->get('grade_id'),
            'learningLanguage' => $errors->get('learning_language'),
            'learningGoals' => $errors->get('learning_goals'),
            'guardianName' => $errors->get('guardian_name'),
            'guardianPhone' => $errors->get('guardian_phone'),
            'avatar' => $errors->get('avatar'),
        ],
        'status' => [
            'updated' => session('status') === 'profile-updated',
        ],
        'copy' => [
            'updated' => __('Your profile has been updated.'),
            'errorTitle' => __('Please check the highlighted fields.'),
            'memberSinceLabel' => __('Member since'),
            'timezoneNote' => __('All lesson times are shown in Sri Lanka time.'),
            'aboutTitle' => __('About you'),
            'aboutHint' => __('This helps teachers understand how to help.'),
            'gradeLabel' => __('Grade'),
            'gradePlaceholder' => __('Select your grade'),
            'gradeHint' => __('Your lessons, teachers and prices are matched to this grade.'),
            'languageLabel' => __('Learning language'),
            'languageHint' => __('The language your lessons are taught in.'),
            'goalsLabel' => __('Learning goals (optional)'),
            'goalsHint' => __('Anything a teacher should know before your first lesson.'),
            'goalsPlaceholder' => __('e.g. Prepare for board exams, strengthen calculus basics...'),
            'guardianTitle' => __('Parent or guardian (optional)'),
            'guardianHint' => __('Add these if a parent or guardian books lessons on your behalf.'),
            'guardianNameLabel' => __('Guardian name'),
            'guardianPhoneLabel' => __('Guardian phone'),
            'photoTitle' => __('Photo'),
            'photoHint' => __('A friendly photo helps teachers recognise you. JPG, PNG or WebP up to 2 MB.'),
            'photoChoose' => __('Choose a photo'),
            'photoReplace' => __('Choose a different photo'),
            'photoClear' => __('Discard the new photo'),
            'strengthTitle' => __('Profile strength'),
            'strengthHint' => __('Every answer here helps us match you with the right teachers.'),
            'strengthItems' => [
                'grade' => __('Your grade'),
                'language' => __('Learning language'),
                'goals' => __('Learning goals'),
                'guardian' => __('Parent or guardian contact'),
                'photo' => __('Profile photo'),
            ],
            'strengthDone' => __('Added'),
            'strengthTodo' => __('Add'),
            'progressLabel' => __(':percent% complete'),
            'save' => __('Save changes'),
            'saving' => __('Saving...'),
            'saved' => __('Everything is saved.'),
            'unsaved' => __('You have unsaved changes.'),
            'undo' => __('Undo changes'),
        ],
        'urls' => [
            'update' => route('student.profile.update'),
            'dashboard' => route('student.dashboard'),
        ],
    ];

    // JSON_UNESCAPED_SLASHES keeps the URLs readable; the HEX flags keep the
    // island safe inside a <script> tag.
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;

    $selectClasses = 'mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900';
    $textareaClasses = 'mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900';
@endphp
<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center gap-3">
            <span class="hidden h-9 w-9 items-center justify-center rounded-xl bg-primary/10 p-2 text-primary sm:flex">
                <svg class="h-full w-full" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M5 20v-1.2a4.3 4.3 0 014.3-4.3h5.4a4.3 4.3 0 014.3 4.3V20" />
                    <circle cx="12" cy="7.5" r="3.5" />
                </svg>
            </span>
            <div>
                <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">
                    {{ __('My Learning Profile') }}
                </h2>
                <p class="text-xs text-slate-500 dark:text-slate-400">
                    {{ __('What teachers see before your first lesson.') }}
                </p>
            </div>
        </div>
    </x-slot>

    <script type="application/json" id="student-profile-config">@json($profilePayload, $jsonFlags)</script>

    <div id="student-profile-app"></div>

    {{-- Fallback for a browser without JavaScript: the form is a real PUT form
         either way, so the same field names and values are submitted. --}}
    <noscript>
        <div class="mx-auto max-w-3xl space-y-6">
            @if (session('status') === 'profile-updated')
                <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                    {{ __('Your profile has been updated.') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="rounded-2xl border border-rose-200/80 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
                    <p class="font-semibold">{{ __('Please check the highlighted fields.') }}</p>
                    <ul class="mt-1 list-inside list-disc space-y-0.5">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('student.profile.update') }}" enctype="multipart/form-data" class="space-y-6">
                @csrf
                @method('PUT')

                <div class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('About you') }}</h3>

                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <div>
                            <x-input-label for="grade_id" :value="__('Grade')" />
                            <select id="grade_id" name="grade_id" class="{{ $selectClasses }}">
                                <option value="">{{ __('Select your grade') }}</option>
                                @foreach ($levels as $level)
                                    <optgroup label="{{ $level->name }}">
                                        @foreach ($level->grades as $grade)
                                            <option value="{{ $grade->id }}" @selected($selectedGrade === $grade->id)>{{ $grade->label }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('grade_id')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="learning_language" :value="__('Learning language')" />
                            <select id="learning_language" name="learning_language" class="{{ $selectClasses }}">
                                <option value="">{{ __('Select a language') }}</option>
                                @foreach (config('studylikepro.student_learning_languages') as $language)
                                    <option value="{{ $language }}" @selected(old('learning_language', $profile?->learning_language) === $language)>{{ $language }}</option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('learning_language')" class="mt-2" />
                        </div>
                    </div>

                    <div class="mt-5">
                        <x-input-label for="learning_goals" :value="__('Learning goals (optional)')" />
                        <textarea id="learning_goals" name="learning_goals" rows="3" class="{{ $textareaClasses }}">{{ old('learning_goals', $profile?->learning_goals) }}</textarea>
                        <x-input-error :messages="$errors->get('learning_goals')" class="mt-2" />
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Parent or guardian (optional)') }}</h3>

                    <div class="mt-5 grid gap-5 sm:grid-cols-2">
                        <div>
                            <x-input-label for="guardian_name" :value="__('Guardian name')" />
                            <x-text-input id="guardian_name" name="guardian_name" type="text" class="mt-1 block w-full" :value="old('guardian_name', $profile?->guardian_name)" />
                            <x-input-error :messages="$errors->get('guardian_name')" class="mt-2" />
                        </div>

                        <div>
                            <x-input-label for="guardian_phone" :value="__('Guardian phone')" />
                            <x-text-input id="guardian_phone" name="guardian_phone" type="tel" class="mt-1 block w-full" :value="old('guardian_phone', $profile?->guardian_phone)" />
                            <x-input-error :messages="$errors->get('guardian_phone')" class="mt-2" />
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Photo') }}</h3>

                    <div class="mt-5 flex items-center gap-4">
                        @if ($user->avatarUrl())
                            <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}" class="h-16 w-16 rounded-2xl object-cover shadow-md" />
                        @else
                            <div class="flex h-16 w-16 items-center justify-center rounded-2xl bg-primary text-xl font-bold text-white shadow-md shadow-primary/20">
                                {{ Str::upper(Str::substr($user->name, 0, 2)) }}
                            </div>
                        @endif

                        <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp"
                               class="block w-full text-sm text-slate-500 file:mr-4 file:rounded-xl file:border-0 file:bg-primary/10 file:px-4 file:py-2 file:text-sm file:font-semibold file:text-primary hover:file:bg-primary/20 dark:text-slate-400" />
                    </div>
                    <x-input-error :messages="$errors->get('avatar')" class="mt-2" />
                </div>

                <div class="flex items-center justify-end">
                    <x-primary-button>{{ __('Save changes') }}</x-primary-button>
                </div>
            </form>
        </div>
    </noscript>

    @vite('resources/js/student-profile/main.js')
</x-app-layout>
