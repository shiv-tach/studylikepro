@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('My subjects') }}</h2>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <div class="flex items-start gap-3">
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-xl">🎯</span>
                <div>
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ __('The subjects you are focusing on') }}</h3>
                    <p class="mt-1 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                        @if ($grade)
                            @if ($baskets->isNotEmpty())
                                {{ __('Every compulsory :grade subject plus the category subjects you picked. Open any subject to see its lessons, teachers and services.', ['grade' => $grade->label]) }}
                            @else
                                {{ __('All the :grade subjects you are studying. Open one to see its lessons, teachers and services.', ['grade' => $grade->label]) }}
                            @endif
                        @else
                            {{ __('Open a subject to see its lessons, teachers and services.') }}
                        @endif
                    </p>
                </div>
            </div>
        </div>

        @if ($subjectGroups->isEmpty())
            <div class="{{ $cardBase }} text-center" :class="{{ $cardTheme }}">
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('Subjects for your grade are being prepared — check back soon!') }}</p>
            </div>
        @else
            @foreach ($subjectGroups as $group)
                <div>
                    @if ($baskets->isNotEmpty())
                        <div class="flex flex-wrap items-center gap-2">
                            <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">
                                {{ $group['basket'] === null ? __('Compulsory subjects') : trim(($group['basket']->icon ?? '').' '.$group['basket']->name) }}
                            </h3>
                            <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide {{ $group['basket'] === null ? 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' : 'bg-primary/10 text-primary' }}">
                                {{ $group['basket'] === null ? __('all students') : __('chosen') }}
                            </span>
                        </div>
                        @if ($group['basket']?->description)
                            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $group['basket']->description }}</p>
                        @endif
                    @endif

                    <div class="mt-3 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($group['subjects'] as $subject)
                            <a href="{{ route('student.subjects.show', array_filter(['subject' => $subject->slug, 'grade' => $grade?->id])) }}"
                               class="group rounded-2xl border border-slate-200/80 bg-white p-6 transition-all hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-lg hover:shadow-primary/5 dark:border-slate-800/80 dark:bg-slate-900 dark:hover:border-primary/40">
                                <div class="flex items-start justify-between">
                                    <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 text-2xl transition-colors group-hover:bg-primary group-hover:text-white">
                                        {{ $subject->icon ?? '📘' }}
                                    </span>
                                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                        {{ $subject->lessons->count() }} {{ Str::plural('lesson', $subject->lessons->count()) }}
                                    </span>
                                </div>
                                <h4 class="mt-4 text-base font-bold text-slate-800 dark:text-slate-100">{{ $subject->name }}</h4>
                                @if ($subject->description)
                                    <p class="mt-1.5 line-clamp-2 text-sm text-slate-500 dark:text-slate-400">{{ $subject->description }}</p>
                                @endif
                                <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-primary">
                                    {{ __('View lessons & teachers') }}
                                    <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</x-app-layout>