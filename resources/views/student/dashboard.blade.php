@php
    $cardBase = 'rounded-2xl border p-6 transition-all hover:shadow-md hover:border-primary/40 dark:hover:border-primary/30';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $chip = 'inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400';
    $profile = Auth::user()->studentProfile;
    $gradeLabel = $profile ? (config('studylikepro.grade_levels')[$profile->grade_level] ?? null) : null;
    $interestsCount = Auth::user()->interestedSubjects()->count();
    $openRequestsCount = Auth::user()->tutoringRequests()->where('status', \App\Enums\RequestStatus::Open->value)->count();
    $upcomingLessonsCount = Auth::user()->bookings()->upcoming()->whereIn('status', ['pending_payment', 'confirmed', 'in_progress'])->count();
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">
            {{ __('My Learning') }}
        </h2>
    </x-slot>

    <div class="space-y-6">
        @if (session('status') === 'profile-completed')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                Your profile is complete — welcome to Studylikepro! 🎉
            </div>
        @endif

        <!-- Welcome banner -->
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-primary via-primary/80 to-purple-600 p-6 text-white shadow-lg shadow-primary/10 dark:shadow-primary/5">
            <div class="relative z-10 max-w-xl">
                <h3 class="text-xl font-bold md:text-2xl">Welcome, {{ Auth::user()->name }}! 👋</h3>
                <p class="mt-1 text-sm text-white/90">Studylikepro connects you with verified tutors for live 1-on-1 lessons. Upload a question, get matched to the right topic, and book a time that works for you.</p>
                @if ($profile)
                    <div class="mt-3 flex flex-wrap gap-2">
                        @if ($gradeLabel)
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold backdrop-blur-sm">🎓 {{ $gradeLabel }}</span>
                        @endif
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold backdrop-blur-sm">🕐 {{ $profile->timezone }}</span>
                    </div>
                @endif
            </div>
            <div class="absolute right-0 top-0 -mr-6 -mt-6 h-36 w-36 rounded-full bg-white/10 blur-xl"></div>
            <div class="absolute right-20 bottom-0 -mb-10 h-28 w-28 rounded-full bg-white/10 blur-lg"></div>
        </div>

        <!-- Quick actions -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <a href="{{ route('student.requests.create') }}" class="group {{ $cardBase }}"
               :class="{{ $cardTheme }}">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Ask</span>
                    <div class="rounded-xl bg-primary/10 p-2.5 text-primary transition-colors duration-300 group-hover:bg-primary group-hover:text-white">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                        </svg>
                    </div>
                </div>
                <h3 class="mt-3 text-sm font-bold text-slate-800 dark:text-slate-100">Ask a question</h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Describe it or upload a photo — AI finds the topic.</p>
            </a>

            <a href="{{ route('student.requests.index') }}" class="group {{ $cardBase }}"
               :class="{{ $cardTheme }}">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Requests</span>
                    @if ($openRequestsCount > 0)
                        <span class="{{ $chip }}">{{ $openRequestsCount }} open</span>
                    @else
                        <span class="{{ $chip }}">None open</span>
                    @endif
                </div>
                <h3 class="mt-3 text-sm font-bold text-slate-800 dark:text-slate-100">My requests</h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Track teacher proposals and your held slots.</p>
            </a>

            <a href="{{ route('student.bookings.index') }}" class="group {{ $cardBase }}"
               :class="{{ $cardTheme }}">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Lessons</span>
                    @if ($upcomingLessonsCount > 0)
                        <span class="{{ $chip }}">{{ $upcomingLessonsCount }} upcoming</span>
                    @else
                        <span class="{{ $chip }}">None booked</span>
                    @endif
                </div>
                <h3 class="mt-3 text-sm font-bold text-slate-800 dark:text-slate-100">My lessons</h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Holds, upcoming lessons and past history.</p>
            </a>

            <a href="{{ route('student.profile') }}" class="group {{ $cardBase }}"
               :class="{{ $cardTheme }}">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Profile</span>
                    <div class="rounded-xl bg-primary/10 p-2.5 text-primary transition-colors duration-300 group-hover:bg-primary group-hover:text-white">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                </div>
                <h3 class="mt-3 text-sm font-bold text-slate-800 dark:text-slate-100">My learning profile</h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Grade, timezone, goals and photo.</p>
            </a>

            <a href="{{ route('settings.theme') }}" class="group {{ $cardBase }}"
               :class="{{ $cardTheme }}">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Appearance</span>
                    <div class="rounded-xl bg-primary/10 p-2.5 text-primary transition-colors duration-300 group-hover:bg-primary group-hover:text-white">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                        </svg>
                    </div>
                </div>
                <h3 class="mt-3 text-sm font-bold text-slate-800 dark:text-slate-100">Theme & appearance</h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Personalize presets, accents, and dark mode.</p>
            </a>

            <a href="{{ route('student.interests.edit') }}" class="group {{ $cardBase }}"
               :class="{{ $cardTheme }}">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Learning</span>
                    @if ($interestsCount > 0)
                        <span class="{{ $chip }}">{{ $interestsCount }} {{ Str::plural('subject', $interestsCount) }}</span>
                    @else
                        <span class="{{ $chip }}">Pick topics</span>
                    @endif
                </div>
                <h3 class="mt-3 text-sm font-bold text-slate-800 dark:text-slate-100">Subjects & topics</h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Tell us what you want help with.</p>
            </a>

            <a href="{{ route('teachers.index') }}" class="group {{ $cardBase }}" :class="{{ $cardTheme }}">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Tutors</span>
                    <span class="{{ $chip }}">Directory</span>
                </div>
                <h3 class="mt-3 text-sm font-bold text-slate-800 dark:text-slate-100">Find a teacher</h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Browse verified tutors by subject and price.</p>
            </a>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Build roadmap -->
            <div class="lg:col-span-2 {{ $cardBase }} hover:shadow-none" :class="{{ $cardTheme }}">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Your path to your first lesson</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Each step ships in a phase of the build plan, so you can watch the platform come together.</p>

                @php
                    $steps = [
                        ['Profile & onboarding', 'Add your learning details so tutors know how to help.', 'Phase 1'],
                        ['Choose subjects & topics', 'Pick what you want help with from the catalog.', 'Phase 2'],
                        ['Upload your question', 'Add a photo and AI suggests the exact topic.', 'Phase 4'],
                        ['Book & pay securely', 'Pick an available time and pay through the payment gateway.', 'Phase 5–6'],
                        ['Join your live lesson', 'Meet your teacher in a private video room.', 'Phase 7'],
                        ['Chat, review & history', 'Stay in touch, rate the lesson, and revisit past lessons.', 'Phase 8–9'],
                    ];
                @endphp

                <ol class="mt-5 space-y-4">
                    @foreach ($steps as $index => [$title, $description, $phase])
                        <li class="flex items-start gap-3">
                            <span class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">{{ $index + 1 }}</span>
                            <div class="flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4 class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $title }}</h4>
                                    <span class="{{ $chip }}">{{ $phase }}</span>
                                </div>
                                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $description }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>

            <!-- Flow overview -->
            <div class="{{ $cardBase }} hover:shadow-none" :class="{{ $cardTheme }}">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">The Studylikepro flow</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">One journey from question to lesson.</p>

                @php
                    $flow = [
                        ['📷', 'Upload your question', 'Snap a photo of what you are stuck on.'],
                        ['🤖', 'AI identifies the topic', 'Know exactly what to learn next.'],
                        ['👨‍🏫', 'Match with a verified teacher', 'Only approved tutors appear.'],
                        ['🕐', 'Choose a time & pay', 'Real availability, secure checkout.'],
                        ['🎥', 'Live lesson, then review', 'Learn 1-on-1 and rate your tutor.'],
                    ];
                @endphp

                <ul class="mt-5 space-y-4">
                    @foreach ($flow as [$emoji, $title, $description])
                        <li class="flex items-start gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-base">{{ $emoji }}</span>
                            <div>
                                <h4 class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $title }}</h4>
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ $description }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>
