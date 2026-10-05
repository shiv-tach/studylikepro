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
        @if (session('status') === 'interests-updated')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                Your interests are saved — we will use them to suggest tutors and match your questions.
            </div>
        @endif

        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <div class="flex items-start gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-xl">🎯</span>
                <div>
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">What do you want help with?</h3>
                    <p class="mt-1 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                        Pick your subjects first, then the specific topics inside them. This shapes your AI question matching and teacher suggestions.
                    </p>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('student.interests.update') }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Subjects</h3>
                <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($subjects as $subject)
                        <label class="flex cursor-pointer items-center gap-3 rounded-xl border px-4 py-3 transition-colors hover:border-primary/40 {{ in_array($subject->id, $selectedSubjects, true) ? 'border-primary/50 bg-primary/5' : 'border-slate-200/80 dark:border-slate-800' }}">
                            <input type="checkbox" name="subjects[]" value="{{ $subject->id }}" @checked(in_array($subject->id, $selectedSubjects, true)) class="{{ $checkboxClasses }}" />
                            <span class="text-lg">{{ $subject->icon ?? '📘' }}</span>
                            <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $subject->name }}</span>
                        </label>
                    @endforeach
                </div>
                @error('subjects')
                    <p class="mt-2 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            @foreach ($subjects as $subject)
                @if ($subject->topics->isEmpty())
                    @continue
                @endif

                <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary/10 text-base">{{ $subject->icon ?? '📘' }}</span>
                        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ $subject->name }} topics</h3>
                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400">optional</span>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3">
                        @foreach ($subject->topics as $topic)
                            <label class="flex cursor-pointer items-center gap-2 rounded-xl border border-slate-200/80 px-3 py-2 text-sm text-slate-700 transition-colors hover:border-primary/40 dark:border-slate-800 dark:text-slate-300">
                                <input type="checkbox" name="topics[]" value="{{ $topic->id }}"
                                       @checked(in_array($topic->id, $selectedTopics, true)) class="{{ $checkboxClasses }}" />
                                {{ $topic->name }}
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
