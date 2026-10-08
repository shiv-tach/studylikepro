@php
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $fieldClasses = 'mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';
    $labelClasses = 'text-xs font-semibold uppercase tracking-wider text-slate-400';

    $selectedSubject = $subjects->firstWhere('slug', $filters['subject'] ?? null);
    $gradeId = $grade?->id;
    $dateFilter = $filters['date'] ?? null;
    $today = now(config('studylikepro.default_display_timezone'))->toDateString();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Find a teacher') }}</h2>
            @if ($grade)
                <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">
                    🎓 {{ __('Matched to :grade', ['grade' => $grade->label]) }}
                </span>
            @endif
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6">
        @if ($grade)
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-xl">🎯</span>
                        <div>
                            <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ __('Teachers for :grade', ['grade' => $grade->label]) }}</h3>
                            <p class="mt-1 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                                {{ __('Every teacher below takes :grade lessons. Narrow the list by subject, language, or the day and time you want your lesson.', ['grade' => $grade->label]) }}
                            </p>
                        </div>
                    </div>
                    <a href="{{ route('student.profile') }}" class="text-sm font-semibold text-primary transition hover:text-primary/80">{{ __('Change grade') }}</a>
                </div>
            </div>
        @else
            <div class="rounded-2xl border border-amber-200/80 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300">
                {{ __('Add your grade to your learning profile and we will only show teachers who take it.') }}
                <a href="{{ route('student.profile') }}" class="font-semibold underline">{{ __('Complete your profile') }}</a>
            </div>
        @endif

        <!-- Filters -->
        <form method="GET" action="{{ route('student.teachers.index') }}" class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="filter-subject" class="{{ $labelClasses }}">{{ __('Subject') }}</label>
                    <select id="filter-subject" name="subject" class="{{ $fieldClasses }}">
                        <option value="">{{ __('Any subject') }}</option>
                        @foreach ($subjects as $subject)
                            <option value="{{ $subject->slug }}" @selected(($filters['subject'] ?? '') === $subject->slug)>{{ $subject->icon }} {{ $subject->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="filter-language" class="{{ $labelClasses }}">{{ __('Language') }}</label>
                    <select id="filter-language" name="language" class="{{ $fieldClasses }}">
                        <option value="">{{ __('Any language') }}</option>
                        @foreach (config('studylikepro.languages') as $language)
                            <option value="{{ $language }}" @selected(($filters['language'] ?? '') === $language)>{{ $language }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="filter-sort" class="{{ $labelClasses }}">{{ __('Sort by') }}</label>
                    <select id="filter-sort" name="sort" class="{{ $fieldClasses }}">
                        <option value="available_soon" @selected($sort === 'available_soon')>{{ __('Earliest availability') }}</option>
                        <option value="rating" @selected($sort === 'rating')>{{ __('Top rated') }}</option>
                        <option value="price_low" @selected($sort === 'price_low')>{{ __('Price: low to high') }}</option>
                        <option value="price_high" @selected($sort === 'price_high')>{{ __('Price: high to low') }}</option>
                        <option value="newest" @selected($sort === 'newest')>{{ __('Newest') }}</option>
                    </select>
                </div>

                <div>
                    <label for="filter-date" class="{{ $labelClasses }}">{{ __('Date') }}</label>
                    <input id="filter-date" name="date" type="date" min="{{ $today }}" value="{{ $dateFilter }}" class="{{ $fieldClasses }}" />
                    <p class="mt-1 text-xs text-slate-400">{{ __('Leave empty for any day') }}</p>
                </div>

                <div>
                    <label for="filter-time-from" class="{{ $labelClasses }}">{{ __('From') }}</label>
                    <input id="filter-time-from" name="time_from" type="time" value="{{ $filters['time_from'] ?? '' }}" class="{{ $fieldClasses }}" />
                </div>

                <div>
                    <label for="filter-time-to" class="{{ $labelClasses }}">{{ __('To') }}</label>
                    <input id="filter-time-to" name="time_to" type="time" value="{{ $filters['time_to'] ?? '' }}" class="{{ $fieldClasses }}" />
                </div>
            </div>

            <div class="mt-5 flex flex-wrap items-center gap-3">
                <button type="submit" class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md shadow-primary/20 transition hover:bg-primary/90">
                    {{ __('Show teachers') }}
                </button>
                <a href="{{ route('student.teachers.index') }}" class="text-sm font-semibold text-slate-500 transition hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200">
                    {{ __('Clear filters') }}
                </a>
                <span class="ml-auto text-sm text-slate-400">{{ $teachers->total() }} {{ Str::plural('teacher', $teachers->total()) }} found</span>
            </div>

            @if ($errors->any())
                <p class="mt-3 text-sm text-red-600 dark:text-red-400">{{ $errors->first() }}</p>
            @endif
        </form>

        <!-- Results -->
        @if ($teachers->isEmpty())
            <div class="{{ $cardBase }} text-center" :class="{{ $cardTheme }}">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 text-2xl">🔍</span>
                <h3 class="mt-4 text-base font-bold text-slate-800 dark:text-slate-100">{{ __('No teachers match yet') }}</h3>
                <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                    {{ $dateFilter
                        ? __('Nobody is free on that day and time yet. Try another day or widen the time window.')
                        : __('Try another subject or language, or clear the filters to see everyone who takes your grade.') }}
                </p>
            </div>
        @else
            <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($teachers as $teacher)
                    @php
                        $slot = $nextSlots[$teacher->id] ?? null;
                        $cardRate = $selectedSubject !== null
                            ? $teacher->effectiveRateFor($selectedSubject, $gradeId)
                            : $teacher->startingRateMinor($gradeId);
                        $gradeLabel = $teacher->gradeScopeLabel($gradesById);
                        $lessonCount = (int) ($teacher->lessons_count ?? 0);
                    @endphp
                    <a href="{{ route('student.teachers.show', $teacher) }}"
                       class="group flex flex-col {{ $cardBase }} transition-all hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-lg hover:shadow-primary/5"
                       :class="{{ $cardTheme }}">
                        <div class="flex items-start gap-3">
                            @if ($teacher->user->avatarUrl())
                                <img src="{{ $teacher->user->avatarUrl() }}" alt="{{ $teacher->user->name }}" class="h-12 w-12 rounded-xl object-cover" />
                            @else
                                <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-base font-bold text-primary">
                                    {{ Str::of($teacher->user->name)->substr(0, 2) }}
                                </span>
                            @endif
                            <div class="min-w-0">
                                <h3 class="truncate text-base font-bold text-slate-800 dark:text-slate-100">{{ $teacher->user->name }}</h3>
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
                                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-slate-500 dark:bg-slate-800 dark:text-slate-400">{{ __('New teacher') }}</span>
                            @endif
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4" /></svg>
                                {{ __('Verified') }}
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

                        @if ($gradeLabel || $lessonCount > 0)
                            <p class="mt-3 text-xs font-medium text-slate-500 dark:text-slate-400">
                                @if ($gradeLabel){{ $gradeLabel }}@endif
                                @if ($gradeLabel && $lessonCount > 0) · @endif
                                @if ($lessonCount > 0){{ $lessonCount }} {{ Str::plural('lesson', $lessonCount) }}@endif
                            </p>
                        @endif

                        <div class="mt-auto flex items-end justify-between gap-3 pt-5">
                            <div>
                                <p class="text-lg font-extrabold text-slate-800 dark:text-slate-100">{{ $money($cardRate) }}<span class="text-sm font-medium text-slate-400">/hr</span></p>
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                    @if ($slot)
                                        @if ($dateFilter)
                                            {{ __('Available :when', ['when' => $slot['starts_at']->setTimezone($viewerTz)->format('D d M · H:i')]) }}
                                        @else
                                            {{ __('Next: :when', ['when' => $slot['starts_at']->setTimezone($viewerTz)->format('D d M · H:i')]) }}
                                        @endif
                                    @else
                                        {{ __('No open slots in the next 7 days') }}
                                    @endif
                                </p>
                            </div>
                            <span class="inline-flex items-center gap-1 text-sm font-semibold text-primary">
                                {{ __('View') }}
                                <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" /></svg>
                            </span>
                        </div>
                    </a>
                @endforeach
            </div>

            <div>
                {{ $teachers->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
