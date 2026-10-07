@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $checkboxClasses = 'rounded border-slate-300 text-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900';
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Subjects & lessons') }}</h2>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        @if (session('status') === 'subjects-updated')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                Subjects updated. Every subject you just added comes loaded with all of its level's grades and lessons — trim below what you don't teach.
            </div>
        @elseif (session('status') === 'subject-setup-updated')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                Teaching preferences saved.
            </div>
        @endif

        <!-- Subjects -->
        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">What do you teach?</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Select every subject you can help with. Students and AI matching will find you through these.</p>

            <form method="POST" action="{{ route('teacher.subjects.store') }}" class="mt-5">
                @csrf
                @foreach ($subjects->groupBy(fn ($subject) => $subject->educationLevel?->name ?? __('Other')) as $levelName => $levelSubjects)
                    <p class="mt-4 text-xs font-semibold uppercase tracking-wider text-slate-400 first:mt-0">{{ $levelName }}</p>
                    <div class="mt-2 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($levelSubjects as $subject)
                            <label class="flex cursor-pointer items-center gap-3 rounded-xl border px-4 py-3 transition-colors hover:border-primary/40 {{ $selectedSubjects->has($subject->id) ? 'border-primary/50 bg-primary/5' : 'border-slate-200/80 dark:border-slate-800' }}">
                                <input type="checkbox" name="subjects[]" value="{{ $subject->id }}" @checked($selectedSubjects->has($subject->id)) class="{{ $checkboxClasses }}" />
                                <span class="text-lg">{{ $subject->icon ?? '📘' }}</span>
                                <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $subject->name }}</span>
                            </label>
                        @endforeach
                    </div>
                @endforeach
                @error('subjects')
                    <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror

                <div class="mt-5 flex justify-end">
                    <x-primary-button>{{ __('Save subjects') }}</x-primary-button>
                </div>
            </form>
        </div>

        <!-- Per-subject setup -->
        @if ($selectedSubjects->isEmpty())
            <div class="{{ $cardBase }} text-center" :class="{{ $cardTheme }}">
                <p class="text-sm text-slate-500 dark:text-slate-400">Select your subjects above and save — then you can choose lessons, grade levels, and rates here.</p>
            </div>
        @else
            @foreach ($subjects as $subject)
                @continue (! $selectedSubjects->has($subject->id))
                @php
                    $pivot = $selectedSubjects->get($subject->id)->pivot;
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
    </div>
</x-app-layout>
