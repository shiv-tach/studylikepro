@php
    // The approval status page. Submitting only starts a review, so this screen
    // never claims the work is finished: green is reserved for a real approval.
    $isLive = $stage === \App\Support\TeacherApproval::STAGE_LIVE;
    $needsAction = $stage === \App\Support\TeacherApproval::STAGE_ACTION;

    $heroClasses = match (true) {
        $isLive => 'border-emerald-200/80 bg-emerald-50/70 dark:border-emerald-900/40 dark:bg-emerald-950/30',
        $needsAction => 'border-rose-200/80 bg-rose-50/70 dark:border-rose-900/40 dark:bg-rose-950/30',
        default => 'border-amber-200/80 bg-amber-50/70 dark:border-amber-900/40 dark:bg-amber-950/30',
    };
    $heroIconClasses = match (true) {
        $isLive => 'bg-emerald-500 shadow-emerald-500/30',
        $needsAction => 'bg-rose-500 shadow-rose-500/30',
        default => 'bg-amber-400 text-amber-950 shadow-amber-400/30',
    };
    $heroTitle = match (true) {
        $isLive => __('You are live'),
        $needsAction => __('Action needed on your application'),
        default => __('Application received — in review'),
    };
    $heroBody = match (true) {
        $isLive => __('Your profile is approved. Students can find you in search and book your open slots.'),
        $needsAction => __('Our team could not approve the application yet. Fix what is noted below and resubmit — you stay in review until then.'),
        default => __('Thanks, :name. Your profile and documents are with our team. You are not visible to students yet and cannot be booked until the application is approved.', ['name' => Auth::user()->displayName()]),
    };
@endphp
<x-onboarding-layout>
    <x-slot name="progress">
        <x-onboarding-progress :current="6" :steps="$steps" />
    </x-slot>

    <div class="mx-auto w-full max-w-3xl space-y-6">
        <div class="rounded-2xl border p-6 text-center {{ $heroClasses }}">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full text-white shadow-lg {{ $heroIconClasses }}">
                @if ($isLive)
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                    </svg>
                @elseif ($needsAction)
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M10.3 3.9l-8 14A2 2 0 004 21h16a2 2 0 001.7-3.1l-8-14a2 2 0 00-3.4 0z" />
                    </svg>
                @else
                    <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l2.5 2.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                @endif
            </span>

            <h1 class="mt-4 text-xl font-bold text-slate-900 dark:text-slate-100 sm:text-2xl">
                {{ $heroTitle }}
            </h1>
            <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                {{ $heroBody }}
            </p>

            @if ($submittedAt)
                <p class="mt-2 text-xs font-semibold {{ $isLive ? 'text-emerald-700 dark:text-emerald-400' : ($needsAction ? 'text-rose-700 dark:text-rose-400' : 'text-amber-700 dark:text-amber-400') }}">
                    {{ __('Submitted :time', ['time' => $submittedAt]) }}
                </p>
            @endif
        </div>

        @if ($needsAction)
            <section class="rounded-2xl border border-rose-200/80 bg-white p-5 dark:border-rose-900/40 dark:bg-slate-900" aria-labelledby="reviewer-notes-title">
                <h2 id="reviewer-notes-title" class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ __('Reviewer notes') }}</h2>
                <p class="mt-2 rounded-xl bg-rose-50 p-3 text-sm leading-relaxed text-rose-800 dark:bg-rose-950/30 dark:text-rose-300">
                    {{ $profile->verification_notes ?: __('Please review your documents and resubmit your application.') }}
                </p>
            </section>
        @endif

        {{-- The three-stage timeline: submitting is step one of three, not the end. --}}
        <section class="rounded-2xl border border-slate-200/80 bg-white p-5 dark:border-slate-800/80 dark:bg-slate-900" aria-labelledby="status-timeline-title">
            <h2 id="status-timeline-title" class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ __('Where your application is') }}</h2>

            <ol class="mt-4 space-y-4">
                <li class="flex items-start gap-3">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-white">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ __('Application submitted') }}</p>
                        @if ($submittedAt)
                            <p class="mt-0.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ $submittedAt }}</p>
                        @endif
                    </div>
                </li>

                <li class="flex items-start gap-3">
                    <span @class([
                        'flex h-7 w-7 shrink-0 items-center justify-center rounded-full',
                        'bg-emerald-500 text-white' => $isLive,
                        'bg-amber-400 text-amber-950' => ! $isLive,
                    ])>
                        @if ($isLive)
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                            </svg>
                        @else
                            <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l2.5 2.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                        @endif
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ __('Admin review') }}</p>
                        <p class="mt-0.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                            @if ($isLive)
                                {{ __('Completed.') }}
                            @elseif ($expectedDecisionAt)
                                {{ __('Expected decision by :date.', ['date' => $expectedDecisionAt]) }}
                            @else
                                {{ __('Usually 1–2 business days.') }}
                            @endif
                        </p>
                    </div>
                </li>

                <li class="flex items-start gap-3">
                    <span @class([
                        'flex h-7 w-7 shrink-0 items-center justify-center rounded-full',
                        'bg-emerald-500 text-white' => $isLive,
                        'bg-slate-100 text-slate-400 dark:bg-slate-800 dark:text-slate-500' => ! $isLive,
                    ]) aria-hidden="true">
                        {{ 3 }}
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold {{ $isLive ? 'text-slate-800 dark:text-slate-100' : 'text-slate-500 dark:text-slate-400' }}">{{ __('Live profile') }}</p>
                        <p class="mt-0.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                            {{ __('Approved teachers appear in student search and can be booked.') }}
                        </p>
                    </div>
                </li>
            </ol>

            @if ($isOverdue)
                <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-3 dark:border-amber-900/40 dark:bg-amber-950/30">
                    <p class="text-sm font-semibold text-amber-800 dark:text-amber-300">{{ __('This is taking longer than usual') }}</p>
                    <p class="mt-0.5 text-xs leading-relaxed text-amber-700 dark:text-amber-400">
                        {{ __('The review has passed its expected date. Nothing else is needed from you — contact our team if you would like an update.') }}
                    </p>
                    <a href="{{ route('legal.contact') }}" class="mt-2 inline-flex text-xs font-semibold text-amber-900 underline underline-offset-2 hover:no-underline dark:text-amber-300">
                        {{ __('Contact our team') }}
                    </a>
                </div>
            @endif
        </section>

        @unless ($isLive)
            <section class="rounded-2xl border border-slate-200/80 bg-white p-5 dark:border-slate-800/80 dark:bg-slate-900" aria-labelledby="reviewing-title">
                <h2 id="reviewing-title" class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ __('What we are reviewing') }}</h2>

                <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                    {{ trans_choice(':count document|:count documents', $documentCount, ['count' => $documentCount]) }}
                    @if ($subjects->isNotEmpty())
                        · {{ $subjects->pluck('name')->implode(', ') }}
                    @endif
                </p>

                <div class="mt-3 flex flex-wrap gap-1.5">
                    @foreach ($subjects as $subject)
                        <span @class([
                            'rounded-full px-2.5 py-0.5 text-[11px] font-semibold',
                            'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' => $subject['approved'],
                            'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' => ! $subject['approved'],
                        ])>{{ $subject['name'] }}</span>
                    @endforeach
                </div>
            </section>
        @endunless

        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 dark:border-slate-800/80 dark:bg-slate-900">
            <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ $isLive ? __('Your next steps') : __('While you wait') }}</h2>
            <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                @if ($isLive)
                    {{ __('Add a service, check your weekly hours, and share your profile link so students can find you.') }}
                @else
                    {{ __('You can preview how students will see you, review your documents, or tidy your profile and weekly hours. Nothing is public until approval.') }}
                @endif
            </p>

            <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
                @if ($isLive)
                    <a href="{{ route('teacher.dashboard') }}"
                       class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 transition-colors hover:bg-primary/90">
                        {{ __('Go to dashboard') }}
                        <span aria-hidden="true">→</span>
                    </a>
                    <a href="{{ route('teachers.show', $profile) }}"
                       class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-600 transition-colors hover:border-slate-300 hover:text-slate-800 dark:border-slate-700 dark:text-slate-300">
                        {{ __('View my public profile') }}
                    </a>
                @elseif ($needsAction)
                    <a href="{{ route('teacher.onboarding.verification') }}"
                       class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 transition-colors hover:bg-primary/90">
                        {{ __('Update my application') }}
                        <span aria-hidden="true">→</span>
                    </a>
                    <a href="{{ route('teacher.profile.preview') }}"
                       class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-600 transition-colors hover:border-slate-300 hover:text-slate-800 dark:border-slate-700 dark:text-slate-300">
                        {{ __('Preview my profile') }}
                    </a>
                @else
                    <a href="{{ route('teacher.profile.preview') }}"
                       class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 transition-colors hover:bg-primary/90">
                        {{ __('Preview my public profile') }}
                        <span aria-hidden="true">→</span>
                    </a>
                    <a href="{{ route('teacher.onboarding.verification') }}"
                       class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-600 transition-colors hover:border-slate-300 hover:text-slate-800 dark:border-slate-700 dark:text-slate-300">
                        {{ __('Review my documents') }}
                    </a>
                    <a href="{{ route('teacher.profile') }}"
                       class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-600 transition-colors hover:border-slate-300 hover:text-slate-800 dark:border-slate-700 dark:text-slate-300">
                        {{ __('Edit my profile') }}
                    </a>
                @endif
            </div>
        </div>

        <p class="text-center text-xs text-slate-400">
            {{ __('Need help?') }}
            <a href="{{ route('legal.contact') }}" class="font-semibold text-primary hover:underline">{{ __('Contact our team') }}</a>
        </p>
    </div>
</x-onboarding-layout>
