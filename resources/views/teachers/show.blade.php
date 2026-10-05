@php
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);
    $cardBase = 'rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900';
    $chip = 'inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400';
    $viewerTz = auth()->user()?->studentProfile?->timezone
        ?? auth()->user()?->teacherProfile?->timezone
        ?? config('studylikepro.default_display_timezone');
    $gradeLevels = config('studylikepro.grade_levels');

    $slotsByDate = [];
    foreach ($availability as $slot) {
        $local = $slot['starts_at']->setTimezone($viewerTz);
        $slotsByDate[$local->toDateString()][] = $local;
    }
    $slotsByDate = array_slice($slotsByDate, 0, 7, true);
@endphp

<x-public-layout>
    <x-slot name="title">{{ $teacher->user->name }} — {{ $teacher->headline }}</x-slot>

    <nav class="flex items-center gap-2 text-sm text-slate-400">
        <a href="{{ route('teachers.index') }}" class="font-semibold transition hover:text-primary">Teachers</a>
        <span>/</span>
        <span class="text-slate-500 dark:text-slate-400">{{ $teacher->user->name }}</span>
    </nav>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <!-- Main column -->
        <div class="space-y-6 lg:col-span-2">
            <div class="{{ $cardBase }}">
                <div class="flex flex-wrap items-start gap-4">
                    @if ($teacher->user->avatarUrl())
                        <img src="{{ $teacher->user->avatarUrl() }}" alt="{{ $teacher->user->name }}" class="h-20 w-20 rounded-2xl object-cover" />
                    @else
                        <span class="flex h-20 w-20 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-2xl font-bold text-primary">
                            {{ Str::of($teacher->user->name)->substr(0, 2) }}
                        </span>
                    @endif
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1 class="text-2xl font-extrabold tracking-tight">{{ $teacher->user->name }}</h1>
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4" /></svg>
                                Verified
                            </span>
                        </div>
                        <p class="mt-1 text-slate-600 dark:text-slate-400">{{ $teacher->headline }}</p>

                        <div class="mt-3 flex flex-wrap items-center gap-2 text-xs font-semibold">
                            @if ($teacher->rating_count > 0)
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                                    ★ {{ number_format((float) $teacher->rating_avg, 1) }} <span class="font-medium text-amber-600/80 dark:text-amber-400/80">({{ trans_choice(':count review|:count reviews', $teacher->rating_count) }})</span>
                                </span>
                            @else
                                <span class="{{ $chip }}">No reviews yet</span>
                            @endif
                            <span class="{{ $chip }}">{{ $teacher->lessons_completed_count }} lessons taught</span>
                            <span class="{{ $chip }}">{{ $teacher->experience_years }} yrs experience</span>
                            <span class="{{ $chip }}">{{ $money($teacher->startingRateMinor()) }}/hr</span>
                        </div>
                    </div>
                </div>

                @if ($teacher->bio)
                    <p class="mt-5 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ $teacher->bio }}</p>
                @endif

                <dl class="mt-5 grid gap-4 border-t border-slate-200/80 pt-5 text-sm dark:border-slate-800 sm:grid-cols-3">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Education</dt>
                        <dd class="mt-1 text-slate-700 dark:text-slate-300">{{ $teacher->education }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Languages</dt>
                        <dd class="mt-1 text-slate-700 dark:text-slate-300">{{ implode(', ', $teacher->languages ?? []) }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">Lesson length</dt>
                        <dd class="mt-1 text-slate-700 dark:text-slate-300">{{ $teacher->lessonDuration() }} minutes</dd>
                    </div>
                </dl>
            </div>

            <!-- Subjects & topics -->
            <div class="{{ $cardBase }}">
                <h2 class="text-base font-bold text-slate-800 dark:text-slate-200">Subjects & topics</h2>

                @forelse ($teacher->subjects as $subject)
                    <div class="mt-4 rounded-xl border border-slate-200/80 p-4 dark:border-slate-800">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ $subject->icon ?? '📘' }} {{ $subject->name }}</p>
                            <p class="text-sm font-semibold text-primary">{{ $money($teacher->effectiveRateFor($subject)) }}<span class="text-xs font-medium text-slate-400">/hr</span></p>
                        </div>

                        @php $levels = collect($subject->pivot->grade_levels ?? [])->map(fn ($level) => $gradeLevels[$level] ?? $level); @endphp
                        @if ($levels->isNotEmpty())
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @foreach ($levels as $level)
                                    <span class="{{ $chip }}">{{ $level }}</span>
                                @endforeach
                            </div>
                        @endif

                        @php $topics = $topicsBySubject->get($subject->id, collect()); @endphp
                        @if ($topics->isNotEmpty())
                            <div class="mt-3 flex flex-wrap gap-1.5">
                                @foreach ($topics as $topic)
                                    <span class="rounded-full bg-primary/10 px-2.5 py-0.5 text-[11px] font-semibold text-primary">{{ $topic->name }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">This teacher is still setting up their subjects.</p>
                @endforelse
            </div>

            <!-- Availability -->
            <div class="{{ $cardBase }}">                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-base font-bold text-slate-800 dark:text-slate-200">Availability</h2>
                    <span class="{{ $chip }}">Your time ({{ $viewerTz }})</span>
                </div>

                @if (empty($slotsByDate))
                    <p class="mt-4 rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                        No open slots in the next two weeks — check back soon.
                    </p>
                @else
                    <div class="mt-4 space-y-4">
                        @foreach ($slotsByDate as $date => $slots)
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ \Carbon\CarbonImmutable::parse($date)->format('D, d M') }}</p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach ($slots as $slot)
                                        <span class="{{ $chip }}">{{ $slot->format('H:i') }}</span>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Reviews -->
            <div class="{{ $cardBase }}">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 class="text-base font-bold text-slate-800 dark:text-slate-200">Student reviews</h2>
                    @if ($teacher->rating_count > 0)
                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                            ★ {{ number_format((float) $teacher->rating_avg, 1) }} · {{ trans_choice(':count review|:count reviews', $teacher->rating_count) }}
                        </span>
                    @endif
                </div>

                @if ($teacher->rating_count === 0)
                    <p class="mt-4 rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                        {{ __('No reviews yet — this teacher is new to Studylikepro.') }}
                    </p>
                @else
                    <div class="mt-4 space-y-2">
                        @foreach ($breakdown as $star => $count)
                            @php $totalReviews = array_sum($breakdown); @endphp
                            <div class="flex items-center gap-3 text-xs">
                                <span class="w-8 shrink-0 font-semibold text-slate-500 dark:text-slate-400">{{ $star }} ★</span>
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                    <div class="h-full rounded-full bg-amber-400" style="width: {{ $totalReviews > 0 ? round($count / $totalReviews * 100) : 0 }}%"></div>
                                </div>
                                <span class="w-6 shrink-0 text-right text-slate-400">{{ $count }}</span>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-5 space-y-4 border-t border-slate-200/80 pt-5 dark:border-slate-800">
                        @foreach ($reviews as $review)
                            <div class="rounded-xl border border-slate-200/80 p-4 dark:border-slate-800">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $review->reviewerName() }}</p>
                                    <p class="text-sm text-amber-500">{{ str_repeat('★', $review->rating) }}<span class="text-slate-300 dark:text-slate-600">{{ str_repeat('★', 5 - $review->rating) }}</span></p>
                                </div>
                                @if ($review->comment)
                                    <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">{{ $review->comment }}</p>
                                @endif
                                <p class="mt-2 text-xs text-slate-400">
                                    {{ $review->booking?->topic?->name ?? $review->booking?->subject?->name ?? __('Lesson') }}
                                    · {{ $review->created_at->format('M Y') }}
                                    @if ($review->wasEdited()) · {{ __('edited') }} @endif
                                </p>
                            </div>
                        @endforeach
                    </div>

                    @if ($teacher->rating_count > $reviews->count())
                        <p class="mt-4 text-xs text-slate-400">{{ __('Showing the :count most recent reviews.', ['count' => $reviews->count()]) }}</p>
                    @endif
                @endif
            </div>
        </div>

        <!-- Sidebar -->
        <div class="lg:sticky lg:top-24 lg:self-start">
            <div class="{{ $cardBase }}">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">Starting at</p>
                <p class="mt-1 text-3xl font-extrabold tracking-tight">{{ $money($teacher->startingRateMinor()) }}<span class="text-sm font-medium text-slate-400">/hour</span></p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $teacher->lessonDuration() }}-minute live 1-on-1 lesson.</p>

                @guest
                    <a href="{{ route('login') }}" class="mt-5 block rounded-xl bg-primary px-4 py-2.5 text-center text-sm font-semibold text-white shadow-md shadow-primary/20 transition hover:bg-primary/90">
                        Log in to request a lesson
                    </a>
                    <a href="{{ route('register', ['role' => 'student']) }}" class="mt-2 block rounded-xl border border-slate-300 px-4 py-2.5 text-center text-sm font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                        Create a student account
                    </a>
                @else
                    @can('create', [App\Models\Booking::class, $teacher])
                        <a href="{{ route('student.bookings.create', $teacher) }}"
                           class="mt-5 block rounded-xl bg-primary px-4 py-2.5 text-center text-sm font-semibold text-white shadow-md shadow-primary/20 transition hover:bg-primary/90">
                            {{ __('Book a slot') }}
                        </a>
                        <a href="{{ route('student.requests.create') }}"
                           class="mt-2 block rounded-xl border border-slate-300 px-4 py-2.5 text-center text-sm font-semibold text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                            {{ __('Send lesson request') }}
                        </a>
                        <p class="mt-3 text-xs text-slate-400">{{ __('Pick one of the open slots above, or send your question for the teacher to match a time.') }}</p>
                    @else
                        <button type="button" disabled
                                class="mt-5 w-full cursor-not-allowed rounded-xl bg-primary/40 px-4 py-2.5 text-center text-sm font-semibold text-white">
                            {{ __('Book a slot') }}
                        </button>
                        <p class="mt-3 text-xs text-slate-400">{{ __('Only student accounts can book lessons.') }}</p>
                    @endcan
                @endguest
            </div>
        </div>
    </div>
</x-public-layout>