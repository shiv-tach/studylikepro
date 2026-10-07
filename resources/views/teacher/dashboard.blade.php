@php
    $cardBase = 'rounded-2xl border p-6 transition-all hover:shadow-md hover:border-primary/40 dark:hover:border-primary/30';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $chip = 'inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400';
    $profile = Auth::user()->teacherProfile;
    $status = $profile?->verification_status;
    $subjectsCount = $profile ? $profile->subjects()->count() : 0;
    $statusDot = match ($status) {
        \App\Enums\VerificationStatus::Approved => 'bg-emerald-300',
        \App\Enums\VerificationStatus::Pending => 'bg-amber-300',
        \App\Enums\VerificationStatus::Rejected => 'bg-rose-300',
        default => 'bg-slate-300',
    };
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">
            {{ __('Teacher Dashboard') }}
        </h2>
    </x-slot>

    <div class="space-y-6">
        <!-- Welcome banner -->
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-primary via-primary/80 to-purple-600 p-6 text-white shadow-lg shadow-primary/10 dark:shadow-primary/5">
            <div class="relative z-10 max-w-xl">
                <div class="flex flex-wrap items-center gap-3">
                    <h3 class="text-xl font-bold md:text-2xl">Welcome, {{ Auth::user()->name }}! 👋</h3>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold backdrop-blur-sm">
                        <span class="h-1.5 w-1.5 rounded-full {{ $statusDot }}"></span>
                        Verification: {{ $status?->label() ?? 'Not started' }}
                    </span>
                </div>
                <p class="mt-2 text-sm text-white/90">Set up your teaching profile, get verified, and start receiving tutoring requests from students who need your subjects.</p>
            </div>
            <div class="absolute right-0 top-0 -mr-6 -mt-6 h-36 w-36 rounded-full bg-white/10 blur-xl"></div>
            <div class="absolute right-20 bottom-0 -mb-10 h-28 w-28 rounded-full bg-white/10 blur-lg"></div>
        </div>

        <!-- Quick actions -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <a href="{{ route('teacher.requests.index') }}" class="group {{ $cardBase }}"
               :class="{{ $cardTheme }}">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Requests</span>
                    @if ($openRequestMatches)
                        <span class="inline-flex items-center rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-primary">{{ $openRequestMatches }} waiting</span>
                    @else
                        <span class="{{ $chip }}">None waiting</span>
                    @endif
                </div>
                <h3 class="mt-3 text-sm font-bold text-slate-800 dark:text-slate-100">Request inbox</h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Questions matched to your lessons and hours.</p>
            </a>

            <a href="{{ route('teacher.schedule.index') }}" class="group {{ $cardBase }}"
               :class="{{ $cardTheme }}">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Schedule</span>
                    @if ($upcomingLessonsCount > 0)
                        <span class="inline-flex items-center rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-primary">{{ $upcomingLessonsCount }} booked</span>
                    @else
                        <span class="{{ $chip }}">Nothing booked</span>
                    @endif
                </div>
                <h3 class="mt-3 text-sm font-bold text-slate-800 dark:text-slate-100">My schedule</h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Upcoming lessons, unpaid holds and earnings per lesson.</p>
            </a>

            <a href="{{ route('teacher.profile') }}" class="group {{ $cardBase }}"
               :class="{{ $cardTheme }}">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Profile</span>
                    <div class="rounded-xl bg-primary/10 p-2.5 text-primary transition-colors duration-300 group-hover:bg-primary group-hover:text-white">
                        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                </div>
                <h3 class="mt-3 text-sm font-bold text-slate-800 dark:text-slate-100">Teaching profile</h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Headline, bio, languages and hourly rate.</p>
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

            <a href="{{ route('teacher.subjects.index') }}" class="group {{ $cardBase }}"
               :class="{{ $cardTheme }}">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Teaching</span>
                    @if ($subjectsCount > 0)
                        <span class="{{ $chip }}">{{ $subjectsCount }} {{ Str::plural('subject', $subjectsCount) }}</span>
                    @else
                        <span class="{{ $chip }}">Set up</span>
                    @endif
                </div>
                <h3 class="mt-3 text-sm font-bold text-slate-800 dark:text-slate-100">Subjects & lessons</h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Tag what you teach so students can find you.</p>
            </a>

            <a href="{{ route('teacher.availability.index') }}" class="group {{ $cardBase }}" :class="{{ $cardTheme }}">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Schedule</span>
                    @if ($weeklySlotCount > 0)
                        <span class="{{ $chip }}">{{ $weeklySlotCount }} weekly {{ Str::plural('range', $weeklySlotCount) }}</span>
                    @else
                        <span class="{{ $chip }}">Set up</span>
                    @endif
                </div>
                <h3 class="mt-3 text-sm font-bold text-slate-800 dark:text-slate-100">Availability & pricing</h3>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">Weekly {{ $lessonDuration }}-minute slots and your hourly rate.</p>
            </a>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Verification status -->
            <div class="{{ $cardBase }} hover:shadow-none" :class="{{ $cardTheme }}">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Verification</h3>
                    @if ($status)
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $status->badgeClasses() }}">{{ $status->label() }}</span>
                    @else
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">Not started</span>
                    @endif
                </div>

                <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                    @if ($status === \App\Enums\VerificationStatus::Pending)
                        Your documents were submitted {{ $profile->submitted_at?->diffForHumans() }} and are waiting for admin review. We will notify you once approved.
                    @elseif ($status === \App\Enums\VerificationStatus::Approved)
                        You are verified — students can find you and send tutoring requests.
                    @elseif ($status === \App\Enums\VerificationStatus::Rejected)
                        {{ $profile->verification_notes ?: 'Please review your documents and resubmit.' }}
                    @else
                        Upload your government ID and credentials, then submit your application for review.
                    @endif
                </p>

                <div class="mt-4 flex flex-wrap gap-2">
                    <a href="{{ route('teacher.profile') }}" class="rounded-xl border border-slate-200/80 px-3 py-1.5 text-xs font-semibold text-slate-600 transition-colors hover:border-primary/40 hover:text-primary dark:border-slate-800 dark:text-slate-300">
                        Edit profile
                    </a>
                    <a href="{{ route('teacher.verification') }}" class="rounded-xl bg-primary/10 px-3 py-1.5 text-xs font-semibold text-primary transition-colors hover:bg-primary/20">
                        Verification documents
                    </a>
                </div>
            </div>

            <!-- To go live roadmap -->
            <div class="lg:col-span-2 {{ $cardBase }} hover:shadow-none" :class="{{ $cardTheme }}">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Your path to your first lesson</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Each step ships in a phase of the build plan, so you can watch your teaching workspace come together.</p>

                @php
                    $steps = [
                        ['Profile & documents', 'Tell students who you are and upload verification documents.', 'Phase 1'],
                        ['Subjects & lessons', 'Choose the subjects and lessons you want to teach.', 'Phase 2'],
                        ['Get verified', 'An admin reviews your application and approves you.', 'Phase 2'],
                        ['Availability & pricing', 'Publish weekly slots and set your hourly rate.', 'Phase 3'],
                        ['Receive requests', 'Accept or reject tutoring requests that match your lessons.', 'Phase 4'],
                        ['Teach live & get paid', 'Run lessons in private video rooms and track earnings.', 'Phase 6–7'],
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
        </div>

        <!-- Earnings -->
        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">Earnings</span>
                    <h3 class="mt-1 text-2xl font-bold tracking-tight text-slate-800 dark:text-slate-100">
                        {{ platform_settings()->formatMinor($earnings['available'] ?? 0) }}
                    </h3>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                        {{ __('Available for payout') }} · {{ __(':amount pending until the lesson is delivered', ['amount' => platform_settings()->formatMinor($earnings['pending'] ?? 0)]) }}
                    </p>
                </div>
                <a href="{{ route('teacher.earnings.index') }}"
                   class="inline-flex items-center rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary/90">
                    {{ __('Open earnings') }}
                </a>
            </div>
        </div>
    </div>
</x-app-layout>
