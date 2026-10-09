@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $chip = 'inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400';
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);
    $typeHints = [
        \App\Enums\ServiceType::Lesson->value => 'A fixed pool of lesson time students book in sessions. The price covers the whole plan.',
        \App\Enums\ServiceType::Summary->value => 'A written summary of one lesson, delivered asynchronously.',
        \App\Enums\ServiceType::PaperMarking->value => 'Marking per paper, with a turnaround promise.',
        \App\Enums\ServiceType::PaperDiscussion->value => 'A live one-on-one session discussing a marked paper.',
        \App\Enums\ServiceType::Qna->value => 'A live one-on-one session answering questions on a subject or one lesson.',
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Services & pricing') }}</h2>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        @foreach ([
            'offering-created' => 'Service added to your catalog. Students can now find it on your profile.',
            'offering-updated' => 'Service updated.',
            'offering-deleted' => 'Service removed from your catalog.',
            'offering-toggled' => 'Availability updated.',
        ] as $status => $message)
            @if (session('status') === $status)
                <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                    {{ $message }}
                </div>
            @endif
        @endforeach

        @if (session('status') === 'offering-has-purchases')
            <div class="rounded-2xl border border-amber-200/80 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300">
                This service already has purchases, so it can't be deleted. Deactivate it instead to stop new sales.
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-2xl border border-red-200/80 bg-red-50 p-4 text-sm text-red-800 dark:border-red-900/40 dark:bg-red-950/30 dark:text-red-300">
                Some of your changes couldn't be saved — check the highlighted fields below.
            </div>
        @endif

        @if ($subjects->isEmpty())
            <div class="{{ $cardBase }} text-center" :class="{{ $cardTheme }}">
                <p class="text-sm text-slate-500 dark:text-slate-400">Add subjects first — every service is priced per subject or lesson.</p>
                <a href="{{ route('teacher.subjects.index') }}" class="mt-3 inline-block text-sm font-semibold text-primary transition-colors hover:text-primary/80">
                    {{ __('Set up subjects & lessons') }} →
                </a>
            </div>
        @else
            @foreach ($types as $type)
                @php $typeOfferings = $offerings->get($type->value, collect()); @endphp

                <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-lg">{{ $type->icon() }}</span>
                            <div>
                                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ $type->pluralLabel() }}</h3>
                                <p class="mt-0.5 text-xs text-slate-400">{{ $typeHints[$type->value] }}</p>
                            </div>
                        </div>
                        <span class="{{ $chip }}">{{ $typeOfferings->count() }} {{ $typeOfferings->count() === 1 ? 'offering' : 'offerings' }}</span>
                    </div>

                    @include('teacher.services._form', [
                        'type' => $type,
                        'gradeOptions' => $gradeOptions,
                        'subjectOptions' => $subjectOptions,
                        'lessonOptions' => $lessonOptions,
                        'durations' => $durations,
                        'offering' => null,
                    ])

                    @if ($typeOfferings->isNotEmpty())
                        <div class="mt-6 space-y-3 border-t border-slate-200/80 pt-5 dark:border-slate-800">
                            @foreach ($typeOfferings as $offering)
                                <div class="rounded-xl border border-slate-200/80 p-4 dark:border-slate-800">
                                    <div class="flex flex-wrap items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <p class="text-sm font-bold text-slate-800 dark:text-slate-200">{{ $offering->title }}</p>
                                                <span class="{{ $offering->is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-slate-100 text-slate-400 dark:bg-slate-800' }} inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide">
                                                    {{ $offering->is_active ? __('Active') : __('Inactive') }}
                                                </span>
                                            </div>

                                            <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                                @if ($offering->grade)
                                                    <span class="{{ $chip }}">🎓 {{ $offering->grade->label }}</span>
                                                @endif
                                                @if ($offering->subject)
                                                    <span class="{{ $chip }}">{{ $offering->subject->icon ?? '📘' }} {{ $offering->subject->name }}</span>
                                                @endif
                                                @if ($offering->lesson)
                                                    <span class="{{ $chip }}">{{ $offering->lesson->name }}</span>
                                                @endif
                                                @if ($type === \App\Enums\ServiceType::Lesson && $offering->allocation_minutes)
                                                    <span class="{{ $chip }}">{{ $offering->allocation_minutes }} min total</span>
                                                @endif
                                                @if ($type->isLiveSession() && $offering->default_session_minutes)
                                                    <span class="{{ $chip }}">{{ $offering->default_session_minutes }} min sessions</span>
                                                @endif
                                                @if ($type === \App\Enums\ServiceType::PaperMarking)
                                                    <span class="{{ $chip }}">{{ $offering->max_pages ? $offering->max_pages.' pages' : 'Any pages' }}</span>
                                                    <span class="{{ $chip }}">{{ $offering->turnaround_hours }}h turnaround</span>
                                                @endif
                                                @if ($offering->description)
                                                    <span class="mt-1 w-full text-xs text-slate-500 dark:text-slate-400">{{ $offering->description }}</span>
                                                @endif
                                            </div>
                                        </div>

                                        <div class="text-right">
                                            <p class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ $money((int) $offering->price_minor) }}</p>
                                            <p class="text-[11px] text-slate-400">{{ $type === \App\Enums\ServiceType::Lesson ? 'per plan' : ($type === \App\Enums\ServiceType::PaperMarking ? 'per paper' : 'per session') }}</p>
                                        </div>
                                    </div>

                                    <div class="mt-3 flex flex-wrap items-center gap-2 border-t border-slate-200/60 pt-3 dark:border-slate-800">
                                        <details class="group">
                                            <summary class="cursor-pointer text-xs font-semibold text-primary transition-colors hover:text-primary/80">{{ __('Edit') }}</summary>
                                            <div class="mt-3">
                                                @include('teacher.services._form', [
                                                    'type' => $type,
                                                    'gradeOptions' => $gradeOptions,
                                                    'subjectOptions' => $subjectOptions,
                                                    'lessonOptions' => $lessonOptions,
                                                    'durations' => $durations,
                                                    'offering' => $offering,
                                                ])
                                            </div>
                                        </details>

                                        <form method="POST" action="{{ route('teacher.services.toggle', $offering) }}">
                                            @csrf
                                            <button type="submit" class="text-xs font-semibold text-primary transition-colors hover:text-primary/80">
                                                {{ $offering->is_active ? __('Deactivate') : __('Activate') }}
                                            </button>
                                        </form>

                                        <form method="POST" action="{{ route('teacher.services.destroy', $offering) }}">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-xs font-semibold text-red-600 transition-colors hover:text-red-500 dark:text-red-400">
                                                {{ __('Delete') }}
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endforeach
        @endif
    </div>
</x-app-layout>
