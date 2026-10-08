<x-public-layout>
    <x-slot name="title">Explore subjects</x-slot>

    <div class="max-w-2xl">
        <h1 class="text-3xl font-extrabold tracking-tight sm:text-4xl">Explore subjects</h1>
        <p class="mt-3 text-slate-600 dark:text-slate-400">
            Browse the curriculum our verified teachers cover — pick your level, then your grade, to see its subjects.
        </p>
    </div>

    @if ($levels->isEmpty())
        <div class="mt-10 rounded-2xl border border-slate-200/80 bg-white p-10 text-center dark:border-slate-800/80 dark:bg-slate-900">
            <p class="text-slate-500 dark:text-slate-400">The catalog is being prepared. Check back soon!</p>
        </div>
    @else
        <section class="mt-10">
            <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Step 1 · Choose your level</h2>
            <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($levels as $levelOption)
                    @php $selected = $level?->id === $levelOption->id; @endphp
                    <a href="{{ route('catalog.subjects.index', ['level' => $levelOption->key]) }}"
                       class="group rounded-2xl border bg-white p-5 transition-all dark:bg-slate-900 {{ $selected ? 'border-primary shadow-lg shadow-primary/10 ring-2 ring-primary/20' : 'border-slate-200/80 hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-lg hover:shadow-primary/5 dark:border-slate-800/80 dark:hover:border-primary/40' }}"
                       @if ($selected) aria-current="true" @endif>
                        <div class="flex items-center justify-between">
                            <span class="flex h-11 w-11 items-center justify-center rounded-xl text-xl {{ $selected ? 'bg-primary text-white' : 'bg-primary/10' }}">
                                {{ $levelOption->icon ?? '📘' }}
                            </span>
                            @if ($selected)
                                <svg class="h-5 w-5 text-primary" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                            @endif
                        </div>
                        <h3 class="mt-3 text-base font-bold text-slate-800 dark:text-slate-100">{{ $levelOption->name }}</h3>
                        @if ($levelOption->grade_min !== null)
                            <p class="mt-0.5 text-xs font-medium text-slate-400 dark:text-slate-500">{{ $levelOption->gradeRangeLabel() }}</p>
                        @endif
                    </a>
                @endforeach
            </div>
        </section>

        @if ($level)
            <section class="mt-10">
                <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Step 2 · Choose your grade</h2>
                @if ($level->grades->isEmpty())
                    <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">Grades for {{ $level->name }} are being prepared.</p>
                @else
                    <div class="mt-3 flex flex-wrap gap-2">
                        @foreach ($level->grades as $gradeOption)
                            @php $selected = $grade?->id === $gradeOption->id; @endphp
                            <a href="{{ route('catalog.subjects.index', ['level' => $level->key, 'grade' => $gradeOption->id]) }}"
                               class="rounded-full px-4 py-2 text-sm font-semibold transition-colors {{ $selected ? 'bg-primary text-white shadow-md shadow-primary/20' : 'bg-white text-slate-600 ring-1 ring-slate-200 hover:bg-primary/10 hover:text-primary dark:bg-slate-900 dark:text-slate-300 dark:ring-slate-800 dark:hover:bg-primary/20' }}"
                               @if ($selected) aria-current="true" @endif>
                                {{ $gradeOption->label }}
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif

        @if ($grade)
            <section class="mt-10">
                <h2 class="text-xs font-semibold uppercase tracking-wider text-slate-400">Step 3 · Pick a subject</h2>
                @if ($subjects->isEmpty())
                    <div class="mt-3 rounded-2xl border border-slate-200/80 bg-white p-10 text-center dark:border-slate-800/80 dark:bg-slate-900">
                        <p class="text-slate-500 dark:text-slate-400">No subjects for {{ $level->name }} {{ $grade->label }} yet — check back soon!</p>
                    </div>
                @else
                    @if ($groupedByBasket)
                        <p class="mt-3 max-w-3xl text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                            {{ __('For the O/L exam students take the compulsory subjects and pick one subject from each basket (:baskets). Pick a subject to see its lessons and teachers.', ['baskets' => $baskets->pluck('name')->implode(', ')]) }}
                        </p>
                    @elseif ($baskets->isNotEmpty())
                        <p class="mt-3 max-w-3xl text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                            {{ __('Every subject below is compulsory in :grade; the optional O/L baskets (:baskets) begin in Grades :grades.', ['grade' => $grade->label, 'baskets' => $baskets->pluck('name')->implode(', '), 'grades' => implode('–', $level->basketGradeNumbers())]) }}
                        </p>
                    @endif

                    <div class="{{ $groupedByBasket ? 'mt-6 space-y-8' : 'mt-3' }}">
                        @foreach ($subjectGroups as $group)
                            <div>
                                @if ($groupedByBasket)
                                    <div class="flex flex-wrap items-center gap-2">
                                        <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">
                                            {{ $group['basket'] === null ? __('Compulsory subjects') : trim(($group['basket']->icon ?? '').' '.$group['basket']->name) }}
                                        </h3>
                                        <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide {{ $group['basket'] === null ? 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' : 'bg-primary/10 text-primary' }}">
                                            {{ $group['basket'] === null ? __('all students') : __('pick one') }}
                                        </span>
                                    </div>
                                    @if ($group['basket']?->description)
                                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $group['basket']->description }}</p>
                                    @endif
                                @endif

                                <div class="mt-3 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                                    @foreach ($group['subjects'] as $subject)
                                        <a href="{{ route('catalog.subjects.show', ['subject' => $subject, 'grade' => $grade->id]) }}"
                                           class="group rounded-2xl border border-slate-200/80 bg-white p-6 transition-all hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-lg hover:shadow-primary/5 dark:border-slate-800/80 dark:bg-slate-900 dark:hover:border-primary/40">
                                            <div class="flex items-start justify-between">
                                                <span class="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/10 text-2xl transition-colors group-hover:bg-primary group-hover:text-white">
                                                    {{ $subject->icon ?? '📘' }}
                                                </span>
                                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                                    {{ $subject->lessons->count() }} {{ Str::plural('lesson', $subject->lessons->count()) }}
                                                </span>
                                            </div>
                                            <h3 class="mt-4 text-base font-bold text-slate-800 dark:text-slate-100">{{ $subject->name }}</h3>
                                            @if ($subject->description)
                                                <p class="mt-1.5 line-clamp-2 text-sm text-slate-500 dark:text-slate-400">{{ $subject->description }}</p>
                                            @endif
                                            <span class="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-primary">
                                                View lessons
                                                <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                                </svg>
                                            </span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        @endif
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
