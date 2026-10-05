<x-public-layout>
    <x-slot name="title">Explore subjects</x-slot>

    <div class="max-w-2xl">
        <h1 class="text-3xl font-extrabold tracking-tight sm:text-4xl">Explore subjects</h1>
        <p class="mt-3 text-slate-600 dark:text-slate-400">
            Every lesson starts with a topic. Browse the curriculum our verified teachers cover — then pick what you want help with.
        </p>
    </div>

    @if ($subjects->isEmpty())
        <div class="mt-10 rounded-2xl border border-slate-200/80 bg-white p-10 text-center dark:border-slate-800/80 dark:bg-slate-900">
            <p class="text-slate-500 dark:text-slate-400">The catalog is being prepared. Check back soon!</p>
        </div>
    @else
        <div class="mt-10 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($subjects as $subject)
                <a href="{{ route('catalog.subjects.show', $subject) }}"
                   class="group rounded-2xl border border-slate-200/80 bg-white p-6 transition-all hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-lg hover:shadow-primary/5 dark:border-slate-800/80 dark:bg-slate-900 dark:hover:border-primary/40">
                    <div class="flex items-start justify-between">
                        <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 text-2xl transition-colors group-hover:bg-primary group-hover:text-white">
                            {{ $subject->icon ?? '📘' }}
                        </span>
                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                            {{ $subject->topics_count }} {{ Str::plural('topic', $subject->topics_count) }}
                        </span>
                    </div>
                    <h2 class="mt-4 text-base font-bold text-slate-800 dark:text-slate-100">{{ $subject->name }}</h2>
                    @if ($subject->description)
                        <p class="mt-1.5 line-clamp-2 text-sm text-slate-500 dark:text-slate-400">{{ $subject->description }}</p>
                    @endif
                    <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-primary">
                        View topics
                        <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                        </svg>
                    </span>
                </a>
            @endforeach
        </div>
    @endif

    @guest
        <div class="mt-12 overflow-hidden rounded-2xl bg-gradient-to-r from-primary via-primary/90 to-purple-600 p-8 text-white shadow-xl shadow-primary/20">
            <h2 class="text-xl font-extrabold tracking-tight sm:text-2xl">Ready to start learning?</h2>
            <p class="mt-2 max-w-xl text-sm text-white/90">Create a free student account to book live 1-on-1 lessons with verified teachers.</p>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('register', ['role' => 'student']) }}" class="rounded-xl bg-white px-5 py-2.5 text-sm font-semibold text-primary shadow-md transition hover:bg-slate-100">
                    Get started free
                </a>
                <a href="{{ route('register', ['role' => 'teacher']) }}" class="rounded-xl border border-white/40 bg-white/10 px-5 py-2.5 text-sm font-semibold text-white backdrop-blur-sm transition hover:bg-white/20">
                    Apply as a teacher
                </a>
            </div>
        </div>
    @endguest
</x-public-layout>
