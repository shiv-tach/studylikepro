<x-public-layout>
    <x-slot name="title">{{ $subject->name }}</x-slot>

    <nav class="flex items-center gap-2 text-sm text-slate-400 dark:text-slate-500">
        <a href="{{ $grade !== null ? route('catalog.subjects.index', ['level' => $level->key, 'grade' => $grade->id]) : route('catalog.subjects.index') }}" class="font-medium transition-colors hover:text-primary">Subjects</a>
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="font-medium text-slate-600 dark:text-slate-300">{{ $subject->name }}</span>
    </nav>

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

    <div class="mt-12 rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h2 class="text-base font-bold text-slate-800 dark:text-slate-100">Want help with {{ $subject->name }}?</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Upload a question and we will match you with a verified teacher for a live lesson.</p>
            </div>
            @auth
                <a href="{{ route('dashboard') }}" class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-primary/20 transition hover:bg-primary/90">
                    Go to my dashboard
                </a>
            @else
                <a href="{{ route('register', ['role' => 'student']) }}" class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-primary/20 transition hover:bg-primary/90">
                    Start learning free
                </a>
            @endauth
        </div>
    </div>
</x-public-layout>