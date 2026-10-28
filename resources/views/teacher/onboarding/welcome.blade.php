@php
    // The welcome/resume screen of the teacher onboarding wizard. It sets the
    // expectation before any form: what setup needs, how long it takes, and
    // where a returning teacher left off.
    $hasStarted = $hasStarted ?? false;
    $totalSteps = count($steps);
    $dataSteps = array_values(array_filter($steps, fn (array $step) => in_array($step['key'], \App\Support\TeacherOnboarding::DATA_STEPS, true)));
    $reviewStep = collect($steps)->firstWhere('key', \App\Support\TeacherOnboarding::STEP_REVIEW);
    $completed = count(array_filter($dataSteps, fn (array $step) => $step['done']));
    $nextLabel = collect($steps)->firstWhere('key', $resume)['label'] ?? __('About you');
    $currentNumber = $resume === 'welcome' ? 1 : (int) (collect($steps)->firstWhere('key', $resume)['number'] ?? 1);

    $needs = [
        ['icon' => '📝', 'title' => __('A headline and a short bio'), 'body' => __('What you teach and how you help students.')],
        ['icon' => '🗓️', 'title' => __('The days and hours you teach'), 'body' => __('A suggested week is pre-loaded if you are not sure.')],
        ['icon' => '🪪', 'title' => __('A government ID'), 'body' => __('A passport, national ID card or driving licence.')],
        ['icon' => '📸', 'title' => __('A profile photo'), 'body' => __('A clear, friendly photo — you can add it later.')],
    ];
@endphp
<x-onboarding-layout>
    <x-slot name="progress">
        <x-onboarding-progress :current="$currentNumber" :steps="$steps" />
    </x-slot>

    <div class="space-y-6">
        <div>
            <p class="text-[11px] font-bold uppercase tracking-wider text-primary">{{ __('Teacher setup') }}</p>
            <h1 class="mt-1 text-2xl font-bold leading-tight text-slate-900 dark:text-slate-100">
                {{ $hasStarted ? __('Welcome back, :name', ['name' => $firstName]) : __('Let’s set up your teaching profile') }}
            </h1>
            <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                {{ $hasStarted
                    ? __('Your answers are saved as you go. Pick up wherever you left off — nothing is lost.')
                    : __('About 10 minutes, in four short steps. You can leave and come back at any time — every answer is kept.') }}
            </p>
        </div>

        @if ($hasStarted)
            <div class="rounded-2xl border border-primary/30 bg-primary/5 p-5 dark:border-primary/30 dark:bg-primary/10">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-slate-900 dark:text-slate-100">
                            {{ __('Continue where you left off: :step', ['step' => $nextLabel]) }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ __(':done of :total steps completed', ['done' => $completed, 'total' => count($dataSteps)]) }}
                            @if ($draftSavedAt)
                                · {{ __('Draft saved :time', ['time' => $draftSavedAt]) }}
                            @endif
                        </p>
                    </div>

                    <a href="{{ $resumeUrl }}"
                       class="inline-flex min-h-11 shrink-0 items-center justify-center gap-1.5 rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 transition-colors hover:bg-primary/90">
                        {{ __('Continue setup') }}
                        <span aria-hidden="true">→</span>
                    </a>
                </div>

                <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-white/70 dark:bg-slate-900/60" aria-hidden="true">
                    <div class="h-full rounded-full bg-primary transition-all duration-500" style="width: {{ max(4, (int) round(($completed / max(count($dataSteps), 1)) * 100)) }}%"></div>
                </div>
            </div>
        @else
            <a href="{{ $resumeUrl }}"
               class="inline-flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-primary px-5 py-3 text-sm font-bold text-white shadow-md shadow-primary/20 transition-colors hover:bg-primary/90 sm:w-auto">
                {{ __('Start setup') }}
                <span aria-hidden="true">→</span>
            </a>
        @endif

        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($needs as $need)
                <div class="flex items-start gap-3 rounded-2xl border border-slate-200/80 bg-white p-4 dark:border-slate-800/80 dark:bg-slate-900">
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-base dark:bg-slate-800" aria-hidden="true">{{ $need['icon'] }}</span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $need['title'] }}</p>
                        <p class="mt-0.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ $need['body'] }}</p>
                    </div>
                </div>
            @endforeach
        </div>

        <section class="rounded-2xl border border-slate-200/80 bg-white p-5 dark:border-slate-800/80 dark:bg-slate-900" aria-labelledby="setup-steps-title">
            <h2 id="setup-steps-title" class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ __('What happens in setup') }}</h2>

            <ol class="mt-4 grid gap-3 sm:grid-cols-2">
                @foreach ($dataSteps as $step)                    @php
                        $isNext = $step['key'] === $resume;
                    @endphp

                    <li class="flex items-start gap-3">
                        <span @class([
                            'flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                            'bg-emerald-500 text-white' => $step['done'],
                            'bg-primary text-white' => $isNext && ! $step['done'],
                            'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' => ! $step['done'] && ! $isNext,
                        ])>
                            @if ($step['done'])
                                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                </svg>
                            @else
                                {{ $step['number'] - 1 }}
                            @endif
                        </span>

                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                {{ $step['label'] }}
                                @if ($isNext && ! $step['done'])
                                    <span class="ml-1 rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-primary">{{ __('Next') }}</span>
                                @endif
                            </p>
                            <p class="mt-0.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ $step['hint'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>

            @if ($reviewStep)
                {{-- The review phase is deliberately outside the setup list: it is
                     not something the teacher fills in, and it is what happens
                     after the last form. --}}
                <div class="mt-5 flex items-start gap-3 rounded-xl border border-amber-200/80 bg-amber-50/60 p-3 dark:border-amber-900/40 dark:bg-amber-950/20">
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-amber-400 text-amber-950" aria-hidden="true">
                        <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l2.5 2.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                            {{ __('After you submit: :step', ['step' => $reviewStep['label']]) }}
                        </p>
                        <p class="mt-0.5 text-xs leading-relaxed text-slate-600 dark:text-slate-400">{{ $reviewStep['hint'] }}</p>
                    </div>
                </div>
            @endif
        </section>

        <p class="text-xs leading-relaxed text-slate-400">
            {{ __('Your profile is reviewed by our team after you submit your documents. Reviews usually take 1–2 business days.') }}
        </p>
    </div>
</x-onboarding-layout>
