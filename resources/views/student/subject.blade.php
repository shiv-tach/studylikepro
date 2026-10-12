<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ $subject->name }}</h2>
            @if ($grade)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">
                    🎓 {{ $level?->name }} · {{ $grade->label }}
                </span>
            @endif
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6">
        <a href="{{ route('student.interests.edit') }}" class="inline-flex items-center gap-1 text-sm font-semibold text-slate-500 transition hover:text-primary dark:text-slate-400">
            &larr; {{ __('My subjects') }}
        </a>

        @include('catalog._subject-content', [
            'subject' => $subject,
            'level' => $level,
            'grade' => $grade,
            'lessons' => $lessons,
            'teachers' => $teachers,
            'teacherTotal' => $teacherTotal,
            'nextSlots' => $nextSlots,
            'offeringGroups' => $offeringGroups,
            'gradesById' => $gradesById,
        ])

        <div class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ __('Want help with :subject?', ['subject' => $subject->name]) }}</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Upload a question and we will match you with a verified teacher for a live lesson.') }}</p>
                </div>
                <a href="{{ route('student.requests.create') }}" class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-primary/20 transition hover:bg-primary/90">
                    {{ __('Ask a question') }}
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
