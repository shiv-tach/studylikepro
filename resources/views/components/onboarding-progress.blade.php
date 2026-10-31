@props(['current' => 1, 'steps' => null, 'answered' => null, 'questions' => null, 'question' => null, 'railUnits' => null])

@php
    // Blade decides the labels and the state; this component only decides how a
    // done, current or locked step looks.
    $steps = $steps ?? [];

    $total = count($steps);
    $completedCount = count(array_filter($steps, fn (array $step) => $step['done']));
    $active = collect($steps)->firstWhere('number', $current) ?? ($steps[0] ?? null);

    // The guest survey answers eight questions but only has six rail segments,
    // so it also passes how many answers are already saved. That keeps the bar
    // moving on every screen instead of every second screen.
    $answeredCount = $answered === null ? null : (int) $answered;
    $questionTotal = $questions === null ? null : (int) $questions;

    // The bar measures one unit more than there are questions when the account
    // form still has to be filled in, so it never looks finished too early. The
    // question count above stays the label ("Question 3 of 8").
    $unitTotal = $railUnits === null ? $questionTotal : (int) $railUnits;

    $percent = $answeredCount !== null && $unitTotal
        ? ($answeredCount / $unitTotal) * 100
        : ($total > 0 ? (($current - 1) / $total) * 100 : 0);

    // A phone shows the finished units in emerald and the unit being worked on
    // now in primary, mirroring the segments of the rail.
    $stepShare = $unitTotal ? 100 / $unitTotal : ($total > 0 ? 100 / $total : 0);
    $donePercent = max(0, min(100, (int) round($percent)));
    $activePercent = max(0, min(100 - $donePercent, (int) round($stepShare)));
    $activePercent = $donePercent === 0 && $activePercent === 0 ? 2 : $activePercent;
@endphp

<div {{ $attributes->merge(['class' => 'border-t border-slate-200/70 bg-white/80 dark:border-slate-800/70 dark:bg-slate-950/60']) }}>
    <div class="mx-auto w-full max-w-3xl px-4 py-2.5 sm:px-6 sm:py-3 lg:max-w-5xl xl:max-w-6xl">
        {{-- Compact line for phones: the one thing a small screen needs. --}}
        <div class="flex items-center justify-between gap-3 sm:hidden">
            <p class="min-w-0 text-xs font-semibold text-slate-600 dark:text-slate-300">
                <span class="text-primary">
                    @if ($question !== null && $questionTotal)
                        {{ __('Question :number of :total', ['number' => $question, 'total' => $questionTotal]) }}
                    @elseif ($questionTotal)
                        {{ __('Last step') }}
                    @else
                        {{ __('Step :number of :total', ['number' => $current, 'total' => $total]) }}
                    @endif
                </span>
                <span class="mx-1 text-slate-300 dark:text-slate-600" aria-hidden="true">·</span>
                <span class="truncate align-bottom">{{ $active['label'] ?? '' }}</span>
            </p>
            <p class="shrink-0 text-[11px] font-semibold text-slate-400">
                {{ $unitTotal ? $answeredCount.'/'.$unitTotal : $completedCount.'/'.$total }}
            </p>
        </div>

        <div class="mt-1.5 flex h-1 overflow-hidden rounded-full bg-slate-200/80 dark:bg-slate-800 sm:hidden"
             role="progressbar"
             aria-valuemin="0"
             aria-valuemax="100"
             aria-valuenow="{{ $donePercent }}"
             aria-label="{{ __('Your progress') }}">
            <div class="h-full shrink-0 bg-emerald-400 transition-all duration-500 motion-reduce:transition-none" style="width: {{ $donePercent }}%"></div>
            <div class="h-full shrink-0 bg-primary transition-all duration-500 motion-reduce:transition-none" style="width: {{ $activePercent }}%"></div>
        </div>

        {{-- Full rail from `sm` upwards. --}}
        <ol class="hidden items-start gap-1 sm:flex" aria-label="{{ __('Teacher setup steps') }}">
            @foreach ($steps as $step)
                @php
                    $isCurrent = $step['number'] === $current;
                    $phase = $step['state'] ?? null;
                    // A waiting step is amber, an action-needed step is rose, and
                    // green stays reserved for work that is actually finished.
                    $state = match (true) {
                        $step['done'] => 'done',
                        $phase === 'pending' => 'pending',
                        $phase === 'action' => 'action',
                        $isCurrent => 'current',
                        default => 'todo',
                    };
                @endphp

                <li class="min-w-0 flex-1">
                    <a @if ($step['reachable']) href="{{ $step['url'] }}" @else aria-disabled="true" tabindex="-1" @endif
                       @if ($isCurrent) aria-current="step" @endif
                       class="group flex flex-col gap-1.5 rounded-lg px-1 py-1 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 {{ $step['reachable'] ? '' : 'pointer-events-none opacity-60' }}">
                        <span class="flex items-center gap-1.5">
                            <span @class([
                                'flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] font-bold',
                                'bg-emerald-500 text-white' => $state === 'done',
                                'bg-amber-400 text-amber-950' => $state === 'pending',
                                'bg-rose-500 text-white' => $state === 'action',
                                'bg-primary text-white' => $state === 'current',
                                'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' => $state === 'todo',
                            ])>
                                @if ($state === 'done')
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                    </svg>
                                @elseif ($state === 'pending')
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l2.5 2.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                @elseif ($state === 'action')
                                    <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 9v4m0 4h.01M12 3l9 16H3l9-16z" />
                                    </svg>
                                @else
                                    {{ $step['number'] }}
                                @endif
                            </span>
                            <span class="h-1 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <span @class([
                                    'block h-full rounded-full transition-all duration-500 motion-reduce:transition-none',
                                    'bg-emerald-400' => $state === 'done',
                                    'bg-amber-300' => $state === 'pending',
                                    'bg-rose-400' => $state === 'action',
                                    'bg-primary' => $state === 'current',
                                    'bg-transparent' => $state === 'todo',
                                ]) style="width: {{ max(0, min(100, (int) round(($step['progress'] ?? ($step['done'] ? 1 : 0)) * 100))) }}%"></span>
                            </span>
                        </span>
                        <span @class([
                            'truncate text-[11px] font-semibold leading-4',
                            'text-slate-500 dark:text-slate-400' => ! in_array($state, ['current', 'pending', 'action'], true),
                            'text-primary' => $state === 'current',
                            'text-amber-600 dark:text-amber-400' => $state === 'pending',
                            'text-rose-600 dark:text-rose-400' => $state === 'action',
                        ])>{{ $step['label'] }}</span>
                    </a>
                </li>
            @endforeach
        </ol>
    </div>
</div>
