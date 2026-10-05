@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $checkboxClasses = 'rounded border-slate-300 text-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900';
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Subjects & topics') }}</h2>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        @if (session('status') === 'subjects-updated')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                Subjects updated — now choose the topics you teach for each one.
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
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($subjects as $subject)
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border px-4 py-3 transition-colors hover:border-primary/40 {{ $selectedSubjects->has($subject->id) ? 'border-primary/50 bg-primary/5' : 'border-slate-200/80 dark:border-slate-800' }}">
                            <input type="checkbox" name="subjects[]" value="{{ $subject->id }}" @checked($selectedSubjects->has($subject->id)) class="{{ $checkboxClasses }}" />
                            <span class="text-lg">{{ $subject->icon ?? '📘' }}</span>
                            <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $subject->name }}</span>
                        </label>
                    @endforeach
                </div>
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
                <p class="text-sm text-slate-500 dark:text-slate-400">Select your subjects above and save — then you can choose topics, grade levels, and rates here.</p>
            </div>
        @else
            @foreach ($subjects as $subject)
                @continue (! $selectedSubjects->has($subject->id))
                @php
                    $pivot = $selectedSubjects->get($subject->id)->pivot;
                    $levels = $pivot->grade_levels ?? [];
                    $rate = $pivot->rate_per_hour_minor;
                @endphp

                <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-lg">{{ $subject->icon ?? '📘' }}</span>
                            <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ $subject->name }}</h3>
                        </div>
                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                            {{ count(array_intersect($subject->topics->pluck('id')->all(), $selectedTopics)) }} / {{ $subject->topics->count() }} topics selected
                        </span>
                    </div>

                    <form method="POST" action="{{ route('teacher.subjects.update', $subject) }}" class="mt-5 space-y-5">
                        @csrf

                        <div>
                            <x-input-label :value="__('Topics you teach')" />
                            <div class="mt-2 grid grid-cols-2 gap-2 sm:grid-cols-3">
                                @foreach ($subject->topics as $topic)
                                    <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200/80 px-3 py-2 text-sm text-slate-700 transition-colors hover:border-primary/40 dark:border-slate-800 dark:text-slate-300">
                                        <input type="checkbox" name="topics[]" value="{{ $topic->id }}"
                                               @checked(in_array($topic->id, $selectedTopics, true)) class="{{ $checkboxClasses }}" />
                                        {{ $topic->name }}
                                    </label>
                                @endforeach
                            </div>
                            <x-input-error :messages="$errors->get('topics')" class="mt-2" />
                        </div>

                        <div class="grid gap-5 sm:grid-cols-2">
                            <div>
                                <x-input-label :value="__('Grade levels you support')" />
                                <div class="mt-2 space-y-2">
                                    @foreach (config('studylikepro.grade_levels') as $value => $label)
                                        <label class="flex cursor-pointer items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                                            <input type="checkbox" name="grade_levels[]" value="{{ $value }}"
                                                   @checked(in_array($value, $levels, true)) class="{{ $checkboxClasses }}" />
                                            {{ $label }}
                                        </label>
                                    @endforeach
                                </div>
                                <x-input-error :messages="$errors->get('grade_levels')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="rate-{{ $subject->id }}" :value="__('Rate override (optional)')" />
                                <div class="relative mt-1">
                                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-slate-400">{{ platform_settings()->currencySymbol() }}</span>
                                    <input id="rate-{{ $subject->id }}" name="rate_per_hour" type="number" min="100" max="100000"
                                           value="{{ old('rate_per_hour', $rate ? (int) ($rate / 100) : '') }}"
                                           placeholder="Default rate"
                                           class="block w-full rounded-xl border-slate-300 bg-white pl-8 text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900" />
                                </div>
                                <p class="mt-1 text-xs text-slate-400">Leave empty to use your base rate.</p>
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
