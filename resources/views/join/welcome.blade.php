@php
    // Step 1 of the invite wizard. It has to say one thing the signed-in wizard
    // never needs to: no account exists yet, and none is created until the last
    // step — the details collected here are simply kept while you work.
    $totalSteps = count($steps);
    $completed = count(array_filter($steps, fn (array $step) => $step['done']));
    $nextLabel = collect($steps)->firstWhere('key', $resume)['label'] ?? __('About you');

    $needs = [
        ['icon' => '📝', 'title' => __('A headline and a short bio'), 'body' => __('What you teach and how you help students.')],
        ['icon' => '🗓️', 'title' => __('The days and hours you teach'), 'body' => __('A suggested week is pre-loaded if you are not sure.')],
        ['icon' => '🪪', 'title' => __('A government ID'), 'body' => __('Needed for the verification step — right after you sign in.')],
        ['icon' => '📸', 'title' => __('A profile photo'), 'body' => __('A clear, friendly photo — you can add it later.')],
    ];
@endphp
<x-onboarding-layout :guest="$guest">
    <x-slot name="progress">
        <x-onboarding-progress :current="$currentStep" :steps="$steps" />
    </x-slot>

    <div class="space-y-6">
        <div>
            <p class="text-[11px] font-bold uppercase tracking-wider text-primary">{{ __('Teacher setup') }}</p>
            <h1 class="mt-1 text-2xl font-bold leading-tight text-slate-900 dark:text-slate-100">
                {{ $hasStarted ? __('Continue your teacher setup') : __('Let’s set up your teaching profile') }}
            </h1>
            <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                {{ $hasStarted
                    ? __('Your answers are kept while you work. Pick up wherever you left off.')
                    : __('Three short steps, then you create your account at the end — about 10 minutes in total.') }}
            </p>
        </div>

        <div class="rounded-2xl border border-primary/30 bg-primary/5 p-4 text-sm leading-relaxed text-slate-700 dark:border-primary/30 dark:bg-primary/10 dark:text-slate-300">
            <p class="font-semibold text-slate-900 dark:text-slate-100">{{ __('No account needed yet') }}</p>
            <p class="mt-1">
                {{ __('Fill in your teaching details first. On the last step you choose a password and we text you a code — your account is created only once that code is confirmed.') }}
            </p>
            @if ($inviteExpiresAt)
                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                    {{ __('This invitation is valid until :date.', ['date' => $inviteExpiresAt]) }}
                </p>
            @endif
        </div>

        @if ($hasStarted)
            <div class="rounded-2xl border border-slate-200/80 bg-white p-5 dark:border-slate-800/80 dark:bg-slate-900">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-slate-900 dark:text-slate-100">
                            {{ __('Continue where you left off: :step', ['step' => $nextLabel]) }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ __(':done of :total steps completed', ['done' => $completed, 'total' => $totalSteps]) }}
                            @if ($draftSavedAt)
                                · {{ __('Saved :time', ['time' => $draftSavedAt]) }}
                            @endif
                        </p>
                    </div>

                    <a href="{{ $resumeUrl }}"
                       class="inline-flex min-h-11 shrink-0 items-center justify-center gap-1.5 rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 transition-colors hover:bg-primary/90">
                        {{ __('Continue setup') }}
                        <span aria-hidden="true">→</span>
                    </a>
                </div>

                <div class="mt-4 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800" aria-hidden="true">
                    <div class="h-full rounded-full bg-primary transition-all duration-500" style="width: {{ max(4, (int) round(($completed / max($totalSteps, 1)) * 100)) }}%"></div>
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

        <section class="rounded-2xl border border-slate-200/80 bg-white p-5 dark:border-slate-800/80 dark:bg-slate-900" aria-labelledby="join-steps-title">
            <h2 id="join-steps-title" class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ __('What happens in setup') }}</h2>

            <ol class="mt-4 grid gap-3 sm:grid-cols-2">
                @foreach ($steps as $step)
                    @php
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
                                {{ $step['number'] }}
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

            {{-- Verification needs the account (documents belong to a profile), so
                 it is explained rather than listed as a locked row. --}}
            <div class="mt-5 flex items-start gap-3 rounded-xl border border-amber-200/80 bg-amber-50/60 p-3 dark:border-amber-900/40 dark:bg-amber-950/20">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-amber-400 text-amber-950" aria-hidden="true">
                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4l2.5 2.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </span>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ __('After you create your account') }}</p>
                    <p class="mt-0.5 text-xs leading-relaxed text-slate-600 dark:text-slate-400">
                        {{ __('Upload your ID, pick your subjects and submit them for review — our team usually replies within 1–2 business days.') }}
                    </p>
                </div>
            </div>
        </section>
    </div>
</x-onboarding-layout>
