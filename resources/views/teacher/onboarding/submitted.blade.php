@php
    // The end of the wizard: a clear "we have it" answer instead of a status
    // card, plus what happens next and what the teacher can do meanwhile.
    $nextSteps = [
        ['title' => __('We check your documents'), 'body' => __('A government ID and the subjects you picked. Reviews usually take 1–2 business days.')],
        ['title' => __('You get a notification'), 'body' => __('We email and notify you in the app once your profile is approved or if we need a clearer copy.')],
        ['title' => __('Your profile goes live'), 'body' => __('Approved teachers appear in student search and can be booked for live lessons.')],
    ];
@endphp
<x-onboarding-layout>
    <div class="mx-auto w-full max-w-3xl space-y-6">
        <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50/70 p-6 text-center dark:border-emerald-900/40 dark:bg-emerald-950/30">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-500 text-white shadow-lg shadow-emerald-500/30">
                <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                </svg>
            </span>

            <h1 class="mt-4 text-xl font-bold text-slate-900 dark:text-slate-100 sm:text-2xl">
                {{ __('Application received') }}
            </h1>
            <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                {{ __('Thanks, :name. Your profile and documents are with our team for review.', ['name' => Auth::user()->displayName()]) }}
            </p>
            @if ($submittedAt)
                <p class="mt-2 text-xs font-semibold text-emerald-700 dark:text-emerald-400">
                    {{ __('Submitted :time', ['time' => $submittedAt]) }}
                </p>
            @endif
        </div>

        <section class="rounded-2xl border border-slate-200/80 bg-white p-5 dark:border-slate-800/80 dark:bg-slate-900" aria-labelledby="next-steps-title">
            <h2 id="next-steps-title" class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ __('What happens next') }}</h2>

            <ol class="mt-4 space-y-4">
                @foreach ($nextSteps as $index => $item)
                    <li class="flex items-start gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">{{ $index + 1 }}</span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $item['title'] }}</p>
                            <p class="mt-0.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ $item['body'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>

        <div class="rounded-2xl border border-slate-200/80 bg-white p-5 dark:border-slate-800/80 dark:bg-slate-900">
            <h2 class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ __('While you wait') }}</h2>
            <p class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
                {{ __('Your dashboard is already open. You can add services, review your weekly hours, or upload a clearer document if you notice a mistake.') }}
            </p>

            <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                <a href="{{ route('teacher.dashboard') }}"
                   class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 transition-colors hover:bg-primary/90">
                    {{ __('Go to dashboard') }}
                    <span aria-hidden="true">→</span>
                </a>
                <a href="{{ route('teacher.onboarding.verification') }}"
                   class="inline-flex min-h-11 items-center justify-center rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold text-slate-600 transition-colors hover:border-slate-300 hover:text-slate-800 dark:border-slate-700 dark:text-slate-300">
                    {{ __('Review my documents') }}
                </a>
            </div>
        </div>

        <p class="text-center text-xs text-slate-400">
            {{ __('Need help?') }}
            <a href="{{ route('legal.contact') }}" class="font-semibold text-primary hover:underline">{{ __('Contact our team') }}</a>
        </p>
    </div>
</x-onboarding-layout>
