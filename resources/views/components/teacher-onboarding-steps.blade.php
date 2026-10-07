@props(['current' => 1])

@php
    $profile = Auth::user()->teacherProfile;
    $profileComplete = (bool) $profile?->isComplete();
    $verificationSubmitted = (bool) $profile?->hasSubmittedVerification();

    $steps = [
        [
            'number' => 1,
            'label' => __('Teaching profile'),
            'hint' => __('Headline, bio, languages and hourly rate.'),
            'url' => route('teacher.profile'),
            'done' => $profileComplete,
            'reachable' => true,
        ],
        [
            'number' => 2,
            'label' => __('Verification'),
            'hint' => ! $profileComplete
                ? __('Unlocks once you save your profile.')
                : ($verificationSubmitted
                    ? __('Documents submitted — our team is reviewing them.')
                    : __('Upload your documents and submit them for review.')),
            'url' => route('teacher.verification'),
            'done' => $verificationSubmitted,
            'reachable' => $profileComplete,
        ],
    ];

    $completedCount = count(array_filter($steps, fn (array $step) => $step['done']));
@endphp

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-slate-200/80 bg-white p-5 dark:border-slate-800/80 dark:bg-slate-900']) }}>
    <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Teacher setup') }}</p>
        <p class="text-xs font-semibold {{ $completedCount === 2 ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400' }}">
            {{ __(':completed of 2 steps completed', ['completed' => $completedCount]) }}
        </p>
    </div>

    <ol class="mt-4 grid gap-3 sm:grid-cols-2">
        @foreach ($steps as $step)
            @php
                $status = $step['done']
                    ? __('Completed')
                    : ($step['number'] === $current ? __('In progress') : ($step['reachable'] ? __('Up next') : __('Locked')));

                $cardClasses = match (true) {
                    $step['done'] => 'border-emerald-200/80 bg-emerald-50/60 dark:border-emerald-900/40 dark:bg-emerald-950/20',
                    $step['number'] === $current => 'border-primary/50 bg-primary/5 dark:border-primary/40 dark:bg-primary/10',
                    default => 'border-slate-200/80 dark:border-slate-800/80',
                };

                $circleClasses = match (true) {
                    $step['done'] => 'bg-emerald-500 text-white',
                    $step['number'] === $current => 'bg-primary text-white',
                    default => 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
                };

                $statusClasses = match (true) {
                    $step['done'] => 'text-emerald-600 dark:text-emerald-400',
                    $step['number'] === $current => 'text-primary',
                    default => 'text-slate-400',
                };
            @endphp

            <li>
                <a href="{{ $step['url'] }}"
                   @if (! $step['reachable']) aria-disabled="true" tabindex="-1" @endif
                   @if ($step['number'] === $current) aria-current="step" @endif
                   class="flex items-center gap-3 rounded-xl border p-3.5 transition-colors {{ $cardClasses }} {{ $step['reachable'] ? 'hover:border-primary/60' : 'pointer-events-none opacity-70' }}">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold {{ $circleClasses }}">
                        @if ($step['done'])
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                            </svg>
                        @else
                            {{ $step['number'] }}
                        @endif
                    </span>
                    <span class="min-w-0">
                        <span class="block text-[11px] font-semibold uppercase tracking-wide {{ $statusClasses }}">
                            {{ __('Step :number', ['number' => $step['number']]) }} · {{ $status }}
                        </span>
                        <span class="block truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $step['label'] }}</span>
                        <span class="block text-xs text-slate-500 dark:text-slate-400">{{ $step['hint'] }}</span>
                    </span>
                </a>
            </li>
        @endforeach
    </ol>
</div>
