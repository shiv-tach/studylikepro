@props(['current' => 1, 'steps' => null])

@php
    // Blade decides the labels and the state; this component only decides how a
    // done, current or locked step looks. The same definitions drive the
    // sidebar and the middleware, so the wizard cannot describe itself twice.
    if ($steps === null) {
        $user = Auth::user();
        $user?->loadMissing('onboardingDraft');
        $steps = \App\Support\TeacherOnboarding::steps($user?->teacherProfile, $user?->onboardingDraft);
    }

    $total = count($steps);
    $completedCount = count(array_filter($steps, fn (array $step) => $step['done']));
    $active = collect($steps)->firstWhere('number', $current) ?? ($steps[0] ?? null);
    $percent = $total > 0 ? (int) round((($current - 1) / $total) * 100) : 0;
@endphp

<div {{ $attributes->merge(['class' => 'border-t border-slate-200/70 bg-white/80 dark:border-slate-800/70 dark:bg-slate-950/60']) }}>
    <div class="mx-auto w-full max-w-3xl px-4 py-2.5 sm:px-6 sm:py-3 lg:max-w-5xl xl:max-w-6xl">
        {{-- Compact line for phones: the one thing a small screen needs. --}}
        <div class="flex items-center justify-between gap-3 sm:hidden">
            <p class="min-w-0 text-xs font-semibold text-slate-600 dark:text-slate-300">
                <span class="text-primary">{{ __('Step :number of :total', ['number' => $current, 'total' => $total]) }}</span>
                <span class="mx-1 text-slate-300 dark:text-slate-600" aria-hidden="true">·</span>
                <span class="truncate align-bottom">{{ $active['label'] ?? '' }}</span>
            </p>
            <p class="shrink-0 text-[11px] font-semibold text-slate-400">{{ $completedCount }}/{{ $total }}</p>
        </div>

        <div class="mt-1.5 h-1 overflow-hidden rounded-full bg-slate-200/80 dark:bg-slate-800 sm:hidden" aria-hidden="true">
            <div class="h-full rounded-full bg-primary transition-all duration-500" style="width: {{ max(4, $percent) }}%"></div>
        </div>

        {{-- Full rail from `sm` upwards. --}}
        <ol class="hidden items-start gap-1 sm:flex" aria-label="{{ __('Teacher setup steps') }}">
            @foreach ($steps as $step)
                @php
                    $isCurrent = $step['number'] === $current;
                    $state = $step['done'] ? 'done' : ($isCurrent ? 'current' : 'todo');
                @endphp

                <li class="min-w-0 flex-1">
                    <a @if ($step['reachable']) href="{{ $step['url'] }}" @else aria-disabled="true" tabindex="-1" @endif
                       @if ($isCurrent) aria-current="step" @endif
                       class="group flex flex-col gap-1.5 rounded-lg px-1 py-1 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 {{ $step['reachable'] ? '' : 'pointer-events-none opacity-60' }}">
                        <span class="flex items-center gap-1.5">
                            <span @class([
                                'flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] font-bold',
                                'bg-emerald-500 text-white' => $state === 'done',
                                'bg-primary text-white' => $state === 'current',
                                'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' => $state === 'todo',
                            ])>
                                @if ($state === 'done')
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                @else
                                    {{ $step['number'] }}
                                @endif
                            </span>
                            <span class="h-1 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <span @class([
                                    'block h-full rounded-full',
                                    'bg-emerald-400' => $state === 'done',
                                    'bg-primary' => $state === 'current',
                                    'bg-transparent' => $state === 'todo',
                                ])></span>
                            </span>
                        </span>
                        <span @class([
                            'truncate text-[11px] font-semibold leading-4',
                            'text-slate-500 dark:text-slate-400' => $state !== 'current',
                            'text-primary' => $state === 'current',
                        ])>{{ $step['label'] }}</span>
                    </a>
                </li>
            @endforeach
        </ol>
    </div>
</div>
