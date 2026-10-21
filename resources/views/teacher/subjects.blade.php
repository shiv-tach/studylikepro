@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $checkboxClasses = 'rounded border-slate-300 text-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900';
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);

    $addGroups = $availableSubjects->groupBy('education_level_id');
    $pickerLevels = $levels->filter(fn ($level) => $addGroups->has($level->id))->values();
    $defaultLevelKey = $pickerLevels->first()?->key;
    $pendingRequests = $subjectRequests->filter(
        fn ($subjectRequest) => $subjectRequest->status === \App\Enums\ApprovalStatus::Pending
    );
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Subjects') }}</h2>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        @if (session('status') === 'subjects-requested')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                Subject requests sent. Our team reviews them shortly and you will be notified once they are approved.
            </div>
        @elseif (session('status') === 'subject-request-created')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                Request sent. Our team reviews new subject proposals and will notify you once yours is approved.
            </div>
        @elseif (session('status') === 'subject-request-cancelled')
            <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4 text-sm text-slate-700 dark:border-slate-800/80 dark:bg-slate-900/60 dark:text-slate-200">
                Request cancelled.
            </div>
        @elseif (session('status') === 'no-new-subjects')
            <div class="rounded-2xl border border-amber-200/80 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300">
                Those subjects are already on your list or already requested.
            </div>
        @elseif (session('status') === 'subject-setup-updated')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                Teaching preferences saved.
            </div>
        @endif

        <!-- Approved subjects: lessons, grades and rates -->
        @if ($subjects->isEmpty())
            <div class="{{ $cardBase }} text-center" :class="{{ $cardTheme }}">
                <p class="text-sm text-slate-500 dark:text-slate-400">No approved subjects yet. Add subjects below or request new ones — once the team approves them you can choose lessons, grade levels, and rates here.</p>
            </div>
        @else
            @foreach ($subjects as $subject)
                @php
                    $pivot = $subject->pivot;
                    $gradeScope = $pivot->grade_levels ?? [];
                    $rate = $pivot->rate_per_hour_minor;
                    $gradeRates = collect($pivot->grade_rates ?? [])
                        ->mapWithKeys(fn ($gradeRate, $gradeId) => [(int) $gradeId => (int) $gradeRate])
                        ->all();
                    $defaultRateMajor = (int) (($rate ?? $baseRateMinor) / 100);
                    $panelGrades = $subject->educationLevel?->grades ?? collect();
                    $selectedGradeIds = array_map('intval', $gradeScope);
                    $lessonGroups = $subject->lessons->groupBy('grade_id');
                @endphp

                <div class="{{ $cardBase }}" :class="{{ $cardTheme }}"
                     x-data="{ gradesOn: { @foreach ($panelGrades as $grade){{ $grade->id }}: {{ in_array((int) $grade->id, $selectedGradeIds, true) ? 'true' : 'false' }}, @endforeach } }">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-lg">{{ $subject->icon ?? '📘' }}</span>
                            <div>
                                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ $subject->name }}</h3>
                                @if ($subject->educationLevel)
                                    <p class="text-xs text-slate-400">{{ $subject->educationLevel->name }}</p>
                                @endif
                            </div>
                        </div>
                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                            {{ count(array_intersect($subject->lessons->pluck('id')->all(), $selectedLessons)) }} / {{ $subject->lessons->count() }} lessons selected
                        </span>
                    </div>

                    <form method="POST" action="{{ route('teacher.subjects.update', $subject) }}" class="mt-5 space-y-5">
                        @csrf
                        <input type="hidden" name="sync_grades" value="1">

                        <div>
                            <x-input-label :value="__('Lessons you teach')" />
                            <p class="mt-1 text-xs text-slate-400">{{ __('Every lesson of the grades you support is ticked by default — untick only what you do not cover.') }}</p>
                            <div class="mt-3 space-y-3">
                                @foreach ($panelGrades as $grade)
                                    @php $gradeLessons = $lessonGroups->get($grade->id, collect()); @endphp
                                    @continue($gradeLessons->isEmpty())

                                    <div :class="gradesOn[{{ $grade->id }}] ? '' : 'opacity-40'">
                                        <p class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">{{ $grade->label }}</p>
                                        <div class="mt-1 grid grid-cols-2 gap-2 sm:grid-cols-3">
                                            @foreach ($gradeLessons as $lesson)
                                                <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200/80 px-3 py-2 text-sm text-slate-700 transition-colors hover:border-primary/40 dark:border-slate-800 dark:text-slate-300">
                                                    <input type="checkbox" name="lessons[]" value="{{ $lesson->id }}"
                                                           @checked(in_array($lesson->id, $selectedLessons, true))
                                                           :disabled="! gradesOn[{{ $grade->id }}]" class="{{ $checkboxClasses }}" />
                                                    {{ $lesson->name }}
                                                </label>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach

                                @if ($lessonGroups->isEmpty())
                                    <p class="text-sm text-slate-400">{{ __('No lessons in the catalog for this subject yet.') }}</p>
                                @endif
                            </div>
                            <x-input-error :messages="$errors->get('lessons')" class="mt-2" />
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <x-input-label :value="__('Grades you support & rate')" />
                                <p class="mt-1 text-xs text-slate-400">{{ __('Tick every grade you teach and set its hourly rate. A grade without a rate uses the default rate.') }}</p>
                                <div class="mt-2 space-y-2">
                                    @foreach ($panelGrades as $grade)
                                        @php $gradeRate = $gradeRates[$grade->id] ?? null; @endphp
                                        <label class="flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-slate-200/80 px-3 py-1.5 text-sm text-slate-600 transition-colors hover:border-primary/40 dark:border-slate-800 dark:text-slate-300">
                                            <span class="flex items-center gap-2">
                                                <input type="checkbox" name="grades[]" value="{{ $grade->id }}"
                                                       x-model="gradesOn[{{ $grade->id }}]"
                                                       @checked(in_array((int) $grade->id, $selectedGradeIds, true)) class="{{ $checkboxClasses }}" />
                                                {{ $grade->label }}
                                            </span>
                                            <span class="relative">
                                                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-2.5 text-xs text-slate-400">{{ platform_settings()->currencySymbol() }}</span>
                                                <input type="number" name="grade_rates[{{ $grade->id }}]" min="100" max="100000" step="50"
                                                       value="{{ old("grade_rates.{$grade->id}", $gradeRate ? (int) ($gradeRate / 100) : '') }}"
                                                       placeholder="{{ $defaultRateMajor }}"
                                                       :disabled="! gradesOn[{{ $grade->id }}]"
                                                       class="w-28 rounded-lg border-slate-300 bg-white py-1.5 pl-7 pr-2 text-right text-sm shadow-sm focus:border-primary focus:ring-primary disabled:opacity-40 dark:border-slate-700 dark:bg-slate-900" />
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                                <p class="mt-1 text-xs text-slate-400">{{ __('Unchecking a grade also unselects its lessons and drops its rate when you save.') }}</p>
                                <x-input-error :messages="$errors->get('grades')" class="mt-2" />
                                <x-input-error :messages="$errors->get('grade_rates')" class="mt-2" />
                                <x-input-error :messages="$errors->get('grade_rates.*')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="rate-{{ $subject->id }}" :value="__('Default rate (optional)')" />
                                <div class="relative mt-1">
                                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-slate-400">{{ platform_settings()->currencySymbol() }}</span>
                                    <input id="rate-{{ $subject->id }}" name="rate_per_hour" type="number" min="100" max="100000"
                                           value="{{ old('rate_per_hour', $rate ? (int) ($rate / 100) : '') }}"
                                           placeholder="Default rate"
                                           class="block w-full rounded-xl border-slate-300 bg-white pl-8 text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900" />
                                </div>
                                <p class="mt-1 text-xs text-slate-400">{{ __('Charged for any grade without a rate of its own. Leave empty to use your base rate of :rate/hour.', ['rate' => $money($baseRateMinor)]) }}</p>
                                <x-input-error :messages="$errors->get('rate_per_hour')" class="mt-2" />
                            </div>
                        </div>

                        <div class="flex justify-end">
                            <x-primary-button>{{ __('Save') }} {{ $subject->name }}</x-primary-button>
                        </div>
                    </form>
                </div>
            @endforeach
        @endif

        <!-- Awaiting approval: onboarding picks and open subject requests -->
        @if ($pendingSubjects->isNotEmpty() || $pendingRequests->isNotEmpty())
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Awaiting approval</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Our team approves these before students can find you through them.</p>

                <ul class="mt-4 space-y-2">
                    @foreach ($pendingSubjects as $pendingSubject)
                        <li class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200/80 px-4 py-3 dark:border-slate-800">
                            <span class="flex flex-wrap items-center gap-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200">
                                <span aria-hidden="true">{{ $pendingSubject->icon ?? '📘' }}</span>
                                {{ $pendingSubject->name }}
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $pendingSubject->pivot->status->badgeClasses() }}">
                                    {{ $pendingSubject->pivot->status->label() }}
                                </span>
                            </span>
                        </li>
                    @endforeach

                    @foreach ($pendingRequests as $subjectRequest)
                        <li class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-slate-200/80 px-4 py-3 dark:border-slate-800">
                            <span class="flex flex-wrap items-center gap-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200">
                                <span aria-hidden="true">{{ $subjectRequest->subject?->icon ?? '🆕' }}</span>
                                {{ $subjectRequest->subject?->name ?? $subjectRequest->proposed_name }}
                                @if ($subjectRequest->isNewSubject())
                                    <span class="rounded-full bg-primary/10 px-2.5 py-0.5 text-[11px] font-semibold text-primary">New subject</span>
                                @endif
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $subjectRequest->status->badgeClasses() }}">
                                    {{ $subjectRequest->status->label() }}
                                </span>
                            </span>
                            <form method="POST" action="{{ route('teacher.subjects.request.cancel', $subjectRequest) }}">
                                @csrf
                                <button type="submit" class="text-xs font-semibold text-slate-400 transition-colors hover:text-rose-600 dark:hover:text-rose-400">{{ __('Cancel') }}</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Subjects the admin did not approve -->
        @if ($rejectedSubjects->isNotEmpty())
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Not approved</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">The team did not approve these during your verification. Add them again below to request approval.</p>

                <ul class="mt-4 space-y-2">
                    @foreach ($rejectedSubjects as $rejectedSubject)
                        <li class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-rose-200/60 px-4 py-3 dark:border-rose-900/40">
                            <span class="flex flex-wrap items-center gap-2.5 text-sm font-semibold text-slate-700 dark:text-slate-200">
                                <span aria-hidden="true">{{ $rejectedSubject->icon ?? '📘' }}</span>
                                {{ $rejectedSubject->name }}
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $rejectedSubject->pivot->status->badgeClasses() }}">
                                    {{ $rejectedSubject->pivot->status->label() }}
                                </span>
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <!-- Add catalog subjects; every pick becomes a pending request -->
        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}"
             x-data="{ level: @js($defaultLevelKey), query: '' }">
            <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Add a subject') }}</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Pick catalog subjects to add to your profile — every one needs admin approval before students can find you through it.</p>

            @if ($pickerLevels->isEmpty())
                <p class="mt-4 text-sm text-slate-400">You have every catalog subject already — propose something new below.</p>
            @else
                <form method="POST" action="{{ route('teacher.subjects.add') }}" class="mt-5">
                    @csrf

                    <div class="flex flex-wrap gap-2" role="tablist">
                        @foreach ($pickerLevels as $level)
                            <button type="button" role="tab" :aria-selected="level === @js($level->key) ? 'true' : 'false'"
                                    class="rounded-full px-3.5 py-1.5 text-xs font-bold transition-colors"
                                    :class="level === @js($level->key) ? 'bg-primary text-white shadow-sm shadow-primary/30' : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700'"
                                    @click="level = @js($level->key)">
                                <span class="mr-1" aria-hidden="true">{{ $level->icon }}</span>{{ $level->name }}
                            </button>
                        @endforeach
                    </div>

                    <label class="relative mt-4 block">
                        <span class="sr-only">Search subjects</span>
                        <input type="search" x-model="query" placeholder="Search subjects"
                               class="block w-full rounded-xl border-slate-300 bg-white py-2 pl-3 pr-3 text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900" />
                    </label>

                    @foreach ($pickerLevels as $level)
                        <div x-show="level === @js($level->key)" class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            @foreach ($addGroups->get($level->id, collect()) as $available)
                                <label class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200/80 px-4 py-3 transition-colors hover:border-primary/40 dark:border-slate-800"
                                       x-show="query === '' || @js(Str::lower($available->name)).includes(query.toLowerCase())">
                                    <input type="checkbox" name="subject_ids[]" value="{{ $available->id }}" class="{{ $checkboxClasses }}" />
                                    <span class="text-lg" aria-hidden="true">{{ $available->icon ?? '📘' }}</span>
                                    <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $available->name }}</span>
                                </label>
                            @endforeach
                        </div>
                    @endforeach

                    @error('subject_ids')
                        <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                    @enderror

                    <div class="mt-5 flex justify-end">
                        <x-primary-button>{{ __('Request subjects') }}</x-primary-button>
                    </div>
                </form>
            @endif
        </div>

        <!-- Propose a subject that is not in the catalog -->
        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Request a new subject') }}</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Can't find what you teach? Propose it — if the team approves, it is added to the catalog and assigned to you.</p>

            <form method="POST" action="{{ route('teacher.subjects.request') }}" class="mt-5 space-y-4">
                @csrf
                <div>
                    <x-input-label for="proposed_name" :value="__('Subject name')" />
                    <input id="proposed_name" name="proposed_name" type="text" maxlength="100" value="{{ old('proposed_name') }}"
                           placeholder="e.g. Advanced ICT"
                           class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900" />
                    <x-input-error :messages="$errors->get('proposed_name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="education_level_id" :value="__('Level')" />
                    <select id="education_level_id" name="education_level_id"
                            class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900">
                        <option value="">{{ __('Choose a level') }}</option>
                        @foreach ($levels as $level)
                            <option value="{{ $level->id }}" @selected(old('education_level_id') == $level->id)>{{ $level->name }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('education_level_id')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="note" :value="__('Why do you want to teach it? (optional)')" />
                    <textarea id="note" name="note" rows="3" maxlength="500"
                              placeholder="Your background, syllabus, experience..."
                              class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900">{{ old('note') }}</textarea>
                    <x-input-error :messages="$errors->get('note')" class="mt-2" />
                </div>

                <div class="flex justify-end">
                    <x-primary-button>{{ __('Send request') }}</x-primary-button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
