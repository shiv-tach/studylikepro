@php
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);
    $viewerTz = auth()->user()?->studentProfile?->timezone
        ?? auth()->user()?->teacherProfile?->timezone
        ?? config('studylikepro.default_display_timezone');

    $isStudentViewer = auth()->check() && auth()->user()->isStudent();

    // Signed-in students stay inside their workspace — that page holds the
    // grade-scoped rates and the booking flow; everyone else gets the
    // public profile.
    $teacherUrl = fn ($teacher) => $isStudentViewer
        ? route('student.teachers.show', $teacher)
        : route('teachers.show', $teacher);

    // Students carry on in the workspace directory, everyone else in the
    // public one.
    $directoryUrl = $isStudentViewer
        ? route('student.teachers.index', array_filter(['subject' => $subject->slug]))
        : route('teachers.index', array_filter(['subject' => $subject->slug, 'grade' => $grade?->id]));
    $directoryRootUrl = $isStudentViewer ? route('student.teachers.index') : route('teachers.index');

    $allGradesUrl = $isStudentViewer
        ? route('student.subjects.show', $subject)
        : route('catalog.subjects.show', $subject);

    $servicesTitle = $grade
        ? __('Services for :subject in :grade', ['subject' => $subject->name, 'grade' => $grade->label])
        : __('Services for :subject', ['subject' => $subject->name]);
@endphp

<div class="mt-6 flex flex-wrap items-start justify-between gap-6">
    <div class="flex items-start gap-4">
        <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 text-3xl">{{ $subject->icon ?? '📘' }}</span>
        <div>
            <h1 class="text-2xl font-extrabold tracking-tight sm:text-3xl">{{ $subject->name }}</h1>
            @if ($subject->description)
                <p class="mt-1.5 max-w-2xl text-slate-600 dark:text-slate-400">{{ $subject->description }}</p>
            @endif
            @if ($subject->basket || $grade)
                <div class="mt-3 flex flex-wrap items-center gap-3">
                    @if ($subject->basket)
                        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                            {{ trim(($subject->basket->icon ?? '').' '.$subject->basket->name) }} · {{ __('pick one') }}
                        </span>
                    @endif
                    @if ($grade)
                        <span class="rounded-full bg-primary/10 px-3 py-1 text-xs font-semibold text-primary">{{ $level?->name }} · {{ $grade->label }}</span>
                        <a href="{{ $allGradesUrl }}" class="text-xs font-semibold text-slate-400 transition-colors hover:text-primary">View all grades</a>
                    @endif
                </div>
            @endif
        </div>
    </div>
    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">
        {{ $lessons->count() }} {{ Str::plural('lesson', $lessons->count()) }}
    </span>
</div>

<section class="mt-10">
    <div>
        <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ $servicesTitle }}</h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
            {{ __('Ready-made packages from verified teachers — buy one, or open a teacher to book a live lesson.') }}
        </p>
    </div>

    @if ($offeringGroups->isEmpty())
        <div class="mt-4 rounded-2xl border border-dashed border-slate-300 p-8 text-center dark:border-slate-700">
            <p class="text-slate-500 dark:text-slate-400">
                {{ __('No ready-made services for this subject yet — book a verified teacher for a live lesson instead.') }}
            </p>
            <a href="{{ $directoryUrl }}"
               class="mt-3 inline-block text-sm font-semibold text-primary transition-colors hover:text-primary/80">
                {{ __('Browse teachers') }} →
            </a>
        </div>
    @else
        <div class="mt-5 space-y-8">
            @foreach ($offeringGroups as $group)
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-lg">{{ $group['type']->icon() }}</span>
                        <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ $group['type']->pluralLabel() }}</h3>
                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                            {{ $group['offerings']->count() }}
                        </span>
                    </div>

                    <div class="mt-3 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($group['offerings'] as $offering)
                            @php $offeringTeacher = $offering->teacherProfile; @endphp
                            <div class="flex flex-col rounded-2xl border border-slate-200/80 bg-white p-5 transition-colors hover:border-primary/40 dark:border-slate-800/80 dark:bg-slate-900 dark:hover:border-primary/40">
                                {{-- The seller block leads the card, gig style: photo on the
                                     left with the name and rating stacked beneath it. --}}
                                @if ($offeringTeacher->user->avatarUrl())
                                    <img src="{{ $offeringTeacher->user->avatarUrl() }}" alt="{{ $offeringTeacher->user->name }}" class="h-11 w-11 rounded-xl object-cover" />
                                @else
                                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-sm font-bold text-primary">
                                        {{ Str::of($offeringTeacher->user->name)->substr(0, 2) }}
                                    </span>
                                @endif

                                <div class="mt-3 min-w-0">
                                    <a href="{{ $teacherUrl($offeringTeacher) }}" class="block truncate text-sm font-bold text-primary transition-colors hover:text-primary/80">
                                        {{ $offeringTeacher->user->name }}
                                    </a>

                                    <div class="mt-1.5 flex flex-wrap items-center gap-2 text-xs font-semibold">
                                        @if ($offeringTeacher->rating_count > 0)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300">
                                                ★ {{ number_format((float) $offeringTeacher->rating_avg, 1) }}
                                                <span class="font-medium text-amber-600/80 dark:text-amber-400/80">({{ $offeringTeacher->rating_count }})</span>
                                            </span>
                                        @else
                                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-slate-500 dark:bg-slate-800 dark:text-slate-400">{{ __('New teacher') }}</span>
                                        @endif
                                    </div>
                                </div>

                                <div class="mt-3 flex items-start justify-between gap-3">
                                    <p class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ $offering->title }}</p>
                                    <p class="shrink-0 text-sm font-extrabold text-slate-800 dark:text-slate-100">{{ $money($offering->price_minor) }}</p>
                                </div>

                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    @if ($offering->grade)
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">🎓 {{ $offering->grade->label }}</span>
                                    @endif
                                    @if ($offering->lesson)
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">{{ $offering->lesson->name }}</span>
                                    @endif
                                    @if ($group['type'] === \App\Enums\ServiceType::Lesson && $offering->allocation_minutes)
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">{{ $offering->allocation_minutes }} min total</span>
                                    @endif
                                    @if ($group['type']->isLiveSession() && $offering->default_session_minutes)
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">{{ $offering->default_session_minutes }} min sessions</span>
                                    @endif
                                    @if ($group['type'] === \App\Enums\ServiceType::PaperMarking)
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">{{ $offering->max_pages ? $offering->max_pages.' pages' : __('Any pages') }}</span>
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">{{ $offering->turnaround_hours }}h turnaround</span>
                                    @endif
                                </div>

                                @if ($offering->description)
                                    <p class="mt-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ $offering->description }}</p>
                                @endif

                                <div class="mt-auto pt-4">
                                    @auth
                                        @can('buy', $offering)
                                            @if ($group['type'] === \App\Enums\ServiceType::PaperMarking)
                                                <a href="{{ route('student.marking.create', $offering) }}"
                                                   class="flex w-full items-center justify-center rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-md shadow-primary/20 transition hover:bg-primary/90">
                                                    {{ __('Submit a paper') }}
                                                </a>
                                            @else
                                                <form method="POST" action="{{ route('student.purchases.store', $offering) }}">
                                                    @csrf
                                                    <button type="submit"
                                                            class="w-full rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-md shadow-primary/20 transition hover:bg-primary/90">
                                                        {{ __('Buy now') }}
                                                    </button>
                                                </form>
                                            @endif
                                        @else
                                            <a href="{{ $teacherUrl($offering->teacherProfile) }}"
                                               class="flex w-full items-center justify-center rounded-xl border border-slate-200/80 px-4 py-2 text-sm font-semibold text-slate-600 transition hover:border-primary/40 hover:text-primary dark:border-slate-800 dark:text-slate-300">
                                                {{ __('View teacher') }}
                                            </a>
                                        @endcan
                                    @else
                                        <a href="{{ route('register', ['role' => 'student']) }}"
                                           class="flex w-full items-center justify-center rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-md shadow-primary/20 transition hover:bg-primary/90">
                                            {{ __('Sign up to buy') }}
                                        </a>
                                    @endauth
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</section>

<section class="mt-10">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100">
                Teachers who teach {{ $subject->name }}@if ($grade) in {{ $grade->label }}@endif
            </h2>
            @if ($teacherTotal > 0)
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ $teacherTotal }} verified {{ Str::plural('teacher', $teacherTotal) }} available
                </p>
            @endif
        </div>
        @if ($teacherTotal > $teachers->count())
            <a href="{{ $directoryUrl }}"
               class="text-sm font-semibold text-primary transition-colors hover:text-primary/80">
                See all {{ $teacherTotal }} teachers →
            </a>
        @endif
    </div>

    @if ($teachers->isEmpty())
        <div class="mt-4 rounded-2xl border border-dashed border-slate-300 p-8 text-center dark:border-slate-700">
            <p class="text-slate-500 dark:text-slate-400">
                No verified teachers yet for {{ $subject->name }}@if ($grade) ({{ $grade->label }})@endif — we are onboarding more every week.
            </p>
            <a href="{{ $directoryRootUrl }}" class="mt-3 inline-block text-sm font-semibold text-primary transition-colors hover:text-primary/80">Browse all teachers</a>
        </div>
    @else
        <div class="mt-4 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($teachers as $teacher)
                @php $nextSlot = $nextSlots[$teacher->id] ?? null; @endphp
                <div class="group relative flex flex-col rounded-2xl border border-slate-200/80 bg-white p-6 transition-all hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-lg hover:shadow-primary/5 dark:border-slate-800/80 dark:bg-slate-900 dark:hover:border-primary/40">
                    <a href="{{ $teacherUrl($teacher) }}" class="absolute inset-0 rounded-2xl focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40">
                        <span class="sr-only">{{ $teacher->user->name }}</span>
                    </a>
                    @if ($teacher->user->avatarUrl())
                        <img src="{{ $teacher->user->avatarUrl() }}" alt="{{ $teacher->user->name }}" class="h-12 w-12 rounded-xl object-cover" />
                    @else
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-base font-bold text-primary">
                            {{ Str::of($teacher->user->name)->substr(0, 2) }}
                        </span>
                    @endif

                    <div class="mt-3 min-w-0">
                        <h3 class="truncate text-base font-bold text-slate-800 dark:text-slate-100">{{ $teacher->user->name }}</h3>

                        <div class="mt-1.5 flex flex-wrap items-center gap-2 text-xs font-semibold">
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
                    </div>

                    <p class="mt-2 line-clamp-2 text-sm text-slate-500 dark:text-slate-400">{{ $teacher->headline }}</p>

                    @php
                        $gradeLabel = $teacher->gradeScopeLabelFor($subject, $gradesById);
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
                                $cardRate = $grade !== null
                                    ? $teacher->effectiveRateFor($subject, $grade->id)
                                    : $teacher->cheapestRateFor($subject);
                                $ratesFrom = $grade === null && $cardRate < $teacher->effectiveRateFor($subject);
                            @endphp
                            <p class="text-lg font-extrabold text-slate-800 dark:text-slate-100">@if ($ratesFrom)<span class="text-xs font-medium text-slate-400">{{ __('from') }} </span>@endif{{ $money($cardRate) }}<span class="text-sm font-medium text-slate-400">/hr</span></p>
                            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                @if ($nextSlot)
                                    Next: {{ display_datetime($nextSlot['starts_at']->setTimezone($viewerTz), 'D d M', ' · ') }}
                                @else
                                    No slots in the next 7 days
                                @endif
                            </p>
                        </div>
                        @auth
                            @can('create', [App\Models\Booking::class, $teacher])
                                <a href="{{ route('student.bookings.create', [
                                        'teacherProfile' => $teacher,
                                        'subject_id' => $subject->id,
                                    ]) }}"
                                   class="relative z-10 inline-flex shrink-0 items-center gap-1 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-md shadow-primary/20 transition hover:bg-primary/90">
                                    Book now
                                </a>
                            @else
                                <span class="inline-flex shrink-0 items-center gap-1 text-sm font-semibold text-primary">
                                    View profile
                                    <svg class="h-4 w-4 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                    </svg>
                                </span>
                            @endcan
                        @else
                            <a href="{{ route('register', ['role' => 'student']) }}"
                               class="relative z-10 inline-flex shrink-0 items-center gap-1 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-md shadow-primary/20 transition hover:bg-primary/90">
                                Book now
                            </a>
                        @endauth
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</section>

<section class="mt-10">
    <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100">Lessons</h2>

    @if ($lessons->isEmpty())
        <div class="mt-4 rounded-2xl border border-slate-200/80 bg-white p-10 text-center dark:border-slate-800/80 dark:bg-slate-900">
            @if ($grade)
                <p class="text-slate-500 dark:text-slate-400">No {{ $grade->label }} lessons for this subject yet.</p>
                <a href="{{ $allGradesUrl }}" class="mt-3 inline-block text-sm font-semibold text-primary transition-colors hover:text-primary/80">View all grades</a>
            @else
                <p class="text-slate-500 dark:text-slate-400">Lessons for this subject are being prepared.</p>
            @endif
        </div>
    @else
        <div class="mt-4 space-y-6">
            @foreach ($lessons->groupBy(fn ($lesson) => $lesson->grade?->label ?? __('Other')) as $gradeLabel => $gradeLessons)
                <div>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $gradeLabel }} · {{ $gradeLessons->count() }} {{ Str::plural('lesson', $gradeLessons->count()) }}</h3>
                    <div class="mt-2 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($gradeLessons as $lesson)
                            <div class="flex items-center gap-3 rounded-2xl border border-slate-200/80 bg-white px-4 py-3.5 transition-colors hover:border-primary/40 dark:border-slate-800/80 dark:bg-slate-900 dark:hover:border-primary/40">
                                <span class="h-2 w-2 shrink-0 rounded-full bg-primary"></span>
                                <span class="min-w-0">
                                    <span class="block text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $lesson->name }}</span>
                                    @if ($lesson->name_si)
                                        <span class="block text-xs text-slate-400 dark:text-slate-500">{{ $lesson->name_si }}</span>
                                    @endif
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</section>
