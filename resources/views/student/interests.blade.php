@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $checkboxClasses = 'rounded border-slate-300 text-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900';
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Subjects & lessons') }}</h2>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        @if (session('status') === 'interests-updated')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                Your interests are saved — we will use them to suggest tutors and match your questions.
                @if (session('basketReminder'))
                    <p class="mt-2 font-medium">
                        {{ __('Reminder: Grade 10–11 students usually study one subject from each O/L basket. You have not picked a subject from: :baskets.', ['baskets' => implode(', ', session('basketReminder'))]) }}
                    </p>
                @endif
            </div>
        @endif

        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <div class="flex items-start gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-xl">🎯</span>
                <div>
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">What do you want help with?</h3>
                    <p class="mt-1 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                        @if ($grade)
                            {{ __('These are the :level subjects and the :grade lessons. Pick your subjects first, then the specific lessons inside them — this shapes your AI question matching and teacher suggestions.', ['level' => $grade->educationLevel?->name ?? __('your level'), 'grade' => $grade->label]) }}
                        @else
                            {{ __('Pick your subjects first, then the specific lessons inside them. This shapes your AI question matching and teacher suggestions.') }}
                        @endif
                    </p>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('student.interests.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Subjects</h3>
                    @if ($baskets->isNotEmpty())
                        <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-semibold text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                            {{ __('One subject per basket') }}
                        </span>
                    @endif
                </div>

                @if ($baskets->isNotEmpty())
                    <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                        {{ __('In Grades 10–11 you pick one subject from each O/L basket — Category I, Category II and Category III. Tick the subjects you want help with: your basket choices and any compulsory subjects.') }}
                    </p>
                @endif

                <div class="mt-4 space-y-6">
                    @foreach ($subjectGroups as $group)
                        <div @if ($group['basket']) data-basket-group @endif>
                            @if ($baskets->isNotEmpty())
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4 class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                                        {{ $group['basket'] === null ? __('Compulsory subjects') : trim(($group['basket']->icon ?? '').' '.$group['basket']->name) }}
                                    </h4>
                                    <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide {{ $group['basket'] === null ? 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' : 'bg-primary/10 text-primary' }}">
                                        {{ $group['basket'] === null ? __('all students') : __('pick one') }}
                                    </span>
                                </div>
                                @if ($group['basket']?->description)
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $group['basket']->description }}</p>
                                @endif
                            @endif

                            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                @foreach ($group['subjects'] as $subject)
                                    <label class="flex cursor-pointer items-center gap-3 rounded-xl border px-4 py-3 transition-colors hover:border-primary/40 {{ in_array($subject->id, $selectedSubjects, true) ? 'border-primary/50 bg-primary/5' : 'border-slate-200/80 dark:border-slate-800' }}">
                                        <input type="checkbox" name="subjects[]" value="{{ $subject->id }}"
                                               @checked(in_array($subject->id, $selectedSubjects, true))
                                               @if ($group['basket'])
                                                   @change="if ($event.target.checked) { $event.target.closest('[data-basket-group]').querySelectorAll('input[type=checkbox]').forEach((box) => { if (box !== $event.target) box.checked = false; }); }"
                                               @endif
                                               class="{{ $checkboxClasses }}" />
                                        <span class="text-lg">{{ $subject->icon ?? '📘' }}</span>
                                        <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $subject->name }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                @error('subjects')
                    <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            @foreach ($subjects as $subject)
                @if ($subject->lessons->isEmpty())
                    @continue
                @endif

                <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/10 text-base">{{ $subject->icon ?? '📘' }}</span>
                        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ $subject->name }} lessons</h3>
                        @if ($grade)
                            <span class="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-primary">{{ $grade->label }}</span>
                        @endif
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">optional</span>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3">
                        @foreach ($subject->lessons as $lesson)
                            <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200/80 px-3 py-2 text-sm text-slate-700 transition-colors hover:border-primary/40 dark:border-slate-800 dark:text-slate-300">
                                <input type="checkbox" name="lessons[]" value="{{ $lesson->id }}"
                                       @checked(in_array($lesson->id, $selectedLessons, true)) class="{{ $checkboxClasses }}" />
                                {{ $grade === null && $lesson->grade?->label ? $lesson->grade->label.' · ' : '' }}{{ $lesson->name }}
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach

            <div class="flex items-center justify-end gap-3">
                <a href="{{ route('student.dashboard') }}" class="text-sm font-semibold text-slate-500 transition-colors hover:text-primary dark:text-slate-400">Back to dashboard</a>
                <x-primary-button>{{ __('Save interests') }}</x-primary-button>
            </div>
        </form>
    </div>
</x-app-layout>
