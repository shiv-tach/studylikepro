<x-public-layout>
    <x-slot name="title">{{ $subject->name }}</x-slot>

    <nav class="flex items-center gap-2 text-sm text-slate-400 dark:text-slate-500">
        <a href="{{ route('catalog.subjects.index') }}" class="font-medium transition-colors hover:text-primary">Subjects</a>
        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
        </svg>
        <span class="font-medium text-slate-600 dark:text-slate-300">{{ $subject->name }}</span>
    </nav>

    <div class="mt-6 flex flex-wrap items-start justify-between gap-6">
        <div class="flex items-start gap-4">
            <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 text-3xl">{{ $subject->icon ?? '📘' }}</span>
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $subject->name }}</h1>
                @if ($subject->description)
                    <p class="mt-1.5 max-w-2xl text-slate-600 dark:text-slate-400">{{ $subject->description }}</p>
                @endif
            </div>
        </div>
        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">
            {{ $topics->count() }} {{ Str::plural('topic', $topics->count()) }}
        </span>
    </div>

    @if ($topics->isEmpty())
        <div class="mt-10 rounded-2xl border border-slate-200/80 bg-white p-10 text-center dark:border-slate-800/80 dark:bg-slate-900">
            <p class="text-slate-500 dark:text-slate-400">Topics for this subject are being prepared.</p>
        </div>
    @else
        <div class="mt-8 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($topics as $topic)
                <div class="flex items-center gap-3 rounded-2xl border border-slate-200/80 bg-white px-4 py-3.5 transition-colors hover:border-primary/40 dark:border-slate-800/80 dark:bg-slate-900 dark:hover:border-primary/40">
                    <span class="h-2 w-2 shrink-0 rounded-full bg-primary"></span>
                    <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $topic->name }}</span>
                </div>
            @endforeach
        </div>
    @endif

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
