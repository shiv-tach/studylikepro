@php
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);
    $fieldClasses = 'w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';
    $viewerTz = auth()->user()?->studentProfile?->timezone
        ?? auth()->user()?->teacherProfile?->timezone
        ?? config('studylikepro.default_display_timezone');

    $selectedLevel = $levels->firstWhere('key', $filters['level'] ?? null);
    $selectedSubject = $subjects->firstWhere('slug', $filters['subject'] ?? null);

    // The pickers cascade server-side: level narrows subjects, subject narrows
    // lessons. Lesson values are ids because lesson slugs repeat per grade.
    $subjectOptions = $selectedLevel
        ? $subjects->where('education_level_id', $selectedLevel->id)
        : $subjects;

    $lessonGroups = ($selectedSubject ? $subjectOptions->where('id', $selectedSubject->id) : $subjectOptions)
        ->map(fn ($subject) => ['subject' => $subject, 'lessons' => $subject->lessons])
        ->filter(fn (array $group) => $group['lessons']->isNotEmpty());

    $gradeOptions = $selectedSubject
        ? $selectedSubject->lessons->pluck('grade')->filter()->unique('id')->sortBy('sort_order')->values()
        : ($selectedLevel ? $selectedLevel->grades : $levels->flatMap(fn ($level) => $level->grades));

    $gradeGroups = $levels
        ->map(fn ($level) => ['level' => $level, 'grades' => $gradeOptions->where('education_level_id', $level->id)])
        ->filter(fn (array $group) => $group['grades']->isNotEmpty());
@endphp

<x-public-layout>
    <x-slot name="title">Find a teacher</x-slot>

    <div class="max-w-2xl">
        <h1 class="text-3xl font-extrabold tracking-tight sm:text-4xl">Find your teacher</h1>
        <p class="mt-3 text-slate-600 dark:text-slate-400">
            Every teacher here is verified by our team. Pick your level and subject to see the grades and lessons they teach.
        </p>
    </div>

    <!-- Filters -->
    <form method="GET" action="{{ route('teachers.index') }}"
          class="mt-8 rounded-2xl border border-slate-200/80 bg-white p-5 dark:border-slate-800/80 dark:bg-slate-900">
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="filter-level" class="text-xs font-semibold uppercase tracking-wider text-slate-400">Level</label>
                <select id="filter-level" name="level" class="mt-1 {{ $fieldClasses }}"
                        onchange="this.form.subject.value=''; this.form.lesson.value=''; this.form.grade.value=''; this.form.submit();">
                    <option value="">Any level</option>
                    @foreach ($levels as $level)
                        <option value="{{ $level->key }}" @selected(($filters['level'] ?? '') === $level->key)>{{ $level->icon }} {{ $level->name }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="filter-subject" class="text-xs font-semibold uppercase tracking-wider text-slate-400">Subject</label>
                <select id="filter-subject" name="subject" class="mt-1 {{ $fieldClasses }}"
                        onchange="this.form.lesson.value=''; this.form.submit();">
                    <option value="">Any subject</option>
                    @foreach ($subjectOptions as $subject)
                        <option value="{{ $subject->slug }}" @selected(($filters['subject'] ?? '') === $subject->slug)>
                            {{ $subject->icon }} {{ $subject->name }}@if (! $selectedLevel) — {{ $subject->educationLevel->name }}@endif
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="filter-lesson" class="text-xs font-semibold uppercase tracking-wider text-slate-400">Lesson</label>
                <select id="filter-lesson" name="lesson" class="mt-1 {{ $fieldClasses }}">
                    <option value="">Any lesson</option>
                    @foreach ($lessonGroups as $group)
                        <optgroup label="{{ $group['subject']->name }}@if (! $selectedLevel) — {{ $group['subject']->educationLevel->name }}@endif">
                            @foreach ($group['lessons'] as $lesson)
                                <option value="{{ $lesson->id }}" @selected((string) ($filters['lesson'] ?? '') === (string) $lesson->id)>
                                    {{ $lesson->name }}@if ($lesson->grade) · {{ $lesson->grade->label }}@endif
                                </option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="filter-grade" class="text-xs font-semibold uppercase tracking-wider text-slate-400">Grade</label>
                <select id="filter-grade" name="grade" class="mt-1 {{ $fieldClasses }}">
                    <option value="">Any grade</option>
                    @foreach ($gradeGroups as $group)
                        <optgroup label="{{ $group['level']->name }}">
                            @foreach ($group['grades'] as $grade)
                                <option value="{{ $grade->id }}" @selected((string) ($filters['grade'] ?? '') === (string) $grade->id)>{{ $grade->label }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="filter-language" class="text-xs font-semibold uppercase tracking-wider text-slate-400">Language</label>
                <select id="filter-language" name="language" class="mt-1 {{ $fieldClasses }}">
                    <option value="">Any language</option>
                    @foreach (config('studylikepro.languages') as $language)
                        <option value="{{ $language }}" @selected(($filters['language'] ?? '') === $language)>{{ $language }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="filter-min-rate" class="text-xs font-semibold uppercase tracking-wider text-slate-400">Min {{ platform_settings()->currencySymbol() }}/hr</label>
                    <input id="filter-min-rate" name="min_rate" type="number" min="0" step="50" value="{{ $filters['min_rate'] ?? '' }}" class="mt-1 {{ $fieldClasses }}" />
                </div>
                <div>
                    <label for="filter-max-rate" class="text-xs font-semibold uppercase tracking-wider text-slate-400">Max {{ platform_settings()->currencySymbol() }}/hr</label>
                    <input id="filter-max-rate" name="max_rate" type="number" min="0" step="50" value="{{ $filters['max_rate'] ?? '' }}" class="mt-1 {{ $fieldClasses }}" />
                </div>
            </div>

            <div>
                <label for="filter-weekday" class="text-xs font-semibold uppercase tracking-wider text-slate-400">Available on</label>
                <select id="filter-weekday" name="weekday" class="mt-1 {{ $fieldClasses }}">
                    <option value="">Any day</option>
                    @foreach (config('studylikepro.weekdays') as $day => $label)
                        <option value="{{ $day }}" @selected((string) ($filters['weekday'] ?? '') === (string) $day)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="filter-time-from" class="text-xs font-semibold uppercase tracking-wider text-slate-400">From</label>
                    <input id="filter-time-from" name="time_from" type="time" value="{{ $filters['time_from'] ?? '' }}" class="mt-1 {{ $fieldClasses }}" />
                </div>
                <div>
                    <label for="filter-time-to" class="text-xs font-semibold uppercase tracking-wider text-slate-400">To</label>
                    <input id="filter-time-to" name="time_to" type="time" value="{{ $filters['time_to'] ?? '' }}" class="mt-1 {{ $fieldClasses }}" />
                </div>
            </div>

            <div>
                <label for="filter-sort" class="text-xs font-semibold uppercase tracking-wider text-slate-400">Sort by</label>
                <select id="filter-sort" name="sort" class="mt-1 {{ $fieldClasses }}">
                    <option value="rating" @selected($sort === 'rating')>Top rated</option>
                    <option value="price_low" @selected($sort === 'price_low')>Price: low to high</option>
                    <option value="price_high" @selected($sort === 'price_high')>Price: high to low</option>
                    <option value="newest" @selected($sort === 'newest')>Newest</option>
                </select>
            </div>
        </div>

        <div class="mt-5 flex flex-wrap items-center gap-3">
            <button type="submit" class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-primary/20 transition hover:bg-primary/90">
                Show teachers
            </button>
            <a href="{{ route('teachers.index') }}" class="text-sm font-semibold text-slate-500 transition hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                Clear filters
            </a>
            <span class="ml-auto text-sm text-slate-400">{{ $teachers->total() }} {{ Str::plural('teacher', $teachers->total()) }} found</span>
        </div>

        @if ($errors->any())
            <p class="mt-3 text-sm text-red-600 dark:text-red-400">{{ $errors->first() }}</p>
        @endif
    </form>

    <!-- Results -->
    @if ($teachers->isEmpty())
        <div class="mt-10 rounded-2xl border border-slate-200/80 bg-white p-10 text-center dark:border-slate-800/80 dark:bg-slate-900">
            <p class="text-slate-500 dark:text-slate-400">No verified teachers match those filters yet. Try widening your search.</p>
        </div>
    @else
        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($teachers as $teacher)
                @php $nextSlot = $nextSlots[$teacher->id] ?? null; @endphp
                <a href="{{ route('teachers.show', $teacher) }}"
                   class="group flex flex-col rounded-2xl border border-slate-200/80 bg-white p-6 transition-all hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-lg hover:shadow-primary/5 dark:border-slate-800/80 dark:bg-slate-900 dark:hover:border-primary/40">
                    <div class="flex items-start gap-3">
                        @if ($teacher->user->avatarUrl())
                            <img src="{{ $teacher->user->avatarUrl() }}" alt="{{ $teacher->user->name }}" class="h-12 w-12 rounded-xl object-cover" />
                        @else
                            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-base font-bold text-primary">
                                {{ Str::of($teacher->user->name)->substr(0, 2) }}
                            </span>
                        @endif
                        <div class="min-w-0">
                            <h2 class="truncate text-base font-bold text-slate-800 dark:text-slate-100">{{ $teacher->user->name }}</h2>
                            <p class="mt-0.5 line-clamp-2 text-sm text-slate-500 dark:text-slate-400">{{ $teacher->headline }}</p>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-2 text-xs font-semibold">
                        @if ($teacher->rating_count > 0)
                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                                ★ {{ number_format((float) $teacher->rating_avg, 1) }}
                                <span class="font-medium text-amber-600/80 dark:text-amber-400/80">({{ $teacher->rating_count }})</span>
                            </span>
                        @else
                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-slate-500 dark:bg-slate-800 dark:text-slate-400">New teacher</span>
                        @endif
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4" /></svg>
                            Verified
                        </span>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-1.5">
                        @foreach ($teacher->subjects->take(3) as $subject)
                            <span class="rounded-full bg-primary/10 px-2.5 py-0.5 text-[11px] font-semibold text-primary">{{ $subject->icon }} {{ $subject->name }}</span>
                        @endforeach
                        @if ($teacher->subjects->count() > 3)
                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">+{{ $teacher->subjects->count() - 3 }}</span>
                        @endif
                    </div>

                    @php
                        $gradeLabel = $teacher->gradeScopeLabel($gradesById);
                        $lessonCount = (int) ($teacher->lessons_count ?? 0);
                    @endphp
                    @if ($gradeLabel || $lessonCount > 0)
                        <p class="mt-3 text-xs font-medium text-slate-500 dark:text-slate-400">
                            @if ($gradeLabel){{ $gradeLabel }}@endif
                            @if ($gradeLabel && $lessonCount > 0) · @endif
                            @if ($lessonCount > 0){{ $lessonCount }} {{ Str::plural('lesson', $lessonCount) }}@endif
                        </p>
                    @endif

                    <div class="mt-auto flex items-end justify-between gap-3 pt-5">
                        <div>
                            @php
                                $cardGradeId = filled($filters['grade'] ?? null) ? (int) $filters['grade'] : null;
                                $cardRate = match (true) {
                                    $selectedSubject !== null && $cardGradeId !== null => $teacher->effectiveRateFor($selectedSubject, $cardGradeId),
                                    $selectedSubject !== null => $teacher->cheapestRateFor($selectedSubject),
                                    default => $teacher->startingRateMinor($cardGradeId),
                                };
                            @endphp
                            <p class="text-lg font-extrabold text-slate-800 dark:text-slate-100">{{ $money($cardRate) }}<span class="text-sm font-medium text-slate-400">/hr</span></p>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                @if ($nextSlot)
                                    Next: {{ $nextSlot['starts_at']->setTimezone($viewerTz)->format('D d M · H:i') }}
                                @else
                                    No slots in the next 7 days
                                @endif
                            </p>
                        </div>
                        <span class="inline-flex items-center gap-1 text-sm font-semibold text-primary">
                            View
                            <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                        </span>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-8">
            {{ $teachers->links() }}
        </div>
    @endif

    @guest
        <div class="mt-12 overflow-hidden rounded-2xl bg-gradient-to-r from-primary via-primary/90 to-purple-600 p-8 text-white shadow-xl shadow-primary/20">
            <h2 class="text-xl font-extrabold tracking-tight sm:text-2xl">Sign in to book a lesson</h2>
            <p class="mt-2 max-w-xl text-sm text-white/90">Create a free student account to request lessons, chat with teachers, and manage your bookings.</p>
            <div class="mt-6 flex flex-wrap gap-3">
                <a href="{{ route('register', ['role' => 'student']) }}" class="rounded-xl bg-white px-5 py-2.5 text-sm font-semibold text-primary shadow-md transition hover:bg-slate-100">
                    Get started free
                </a>
                <a href="{{ route('login') }}" class="rounded-xl border border-white/40 bg-white/10 px-5 py-2.5 text-sm font-semibold text-white backdrop-blur-sm transition hover:bg-white/20">
                    Log in
                </a>
            </div>
        </div>
    @endguest
</x-public-layout>