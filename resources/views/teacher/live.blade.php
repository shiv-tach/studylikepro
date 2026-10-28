@php
    // The earned completion moment: this is the first screen that may call the
    // work finished, because an admin has actually approved the profile.
    $nextSteps = [
        ['title' => __('Add a service'), 'body' => __('Lesson plans, paper marking or Q&A — what students can buy from you.'), 'url' => route('teacher.services.index'), 'cta' => __('Open services')],
        ['title' => __('Check your weekly hours'), 'body' => __('Students can only book inside the availability you publish.'), 'url' => route('teacher.availability.index'), 'cta' => __('Review availability')],
        ['title' => __('Share your profile'), 'body' => __('Send your public page to students so they can book their first lesson.'), 'url' => $publicUrl, 'cta' => __('View public profile')],
    ];
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="text-xl font-bold leading-tight text-slate-800 dark:text-slate-100">
            {{ __('You are live') }}
        </h2>
    </x-slot>

    <div class="mx-auto w-full max-w-3xl space-y-6">
        <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50/70 p-6 text-center dark:border-emerald-900/40 dark:bg-emerald-950/30">
            <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-500 text-white shadow-lg shadow-emerald-500/30">
                <svg class="h-7 w-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                </svg>
            </span>

            <h1 class="mt-4 text-xl font-bold text-slate-900 dark:text-slate-100 sm:text-2xl">
                {{ __('Your profile is approved, :name', ['name' => Auth::user()->displayName()]) }}
            </h1>
            <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                {{ __('Students can now find you in search and book your open slots. This is the moment setup turns into teaching.') }}
            </p>

            @if ($approvedSubjects !== [])
                <p class="mt-3 text-xs font-semibold text-emerald-700 dark:text-emerald-400">
                    {{ __('Approved subjects: :subjects', ['subjects' => implode(', ', $approvedSubjects)]) }}
                </p>
            @endif
        </div>

        <section class="rounded-2xl border border-slate-200/80 bg-white p-5 dark:border-slate-800/80 dark:bg-slate-900" aria-labelledby="live-next-steps">
            <h2 id="live-next-steps" class="text-sm font-bold text-slate-900 dark:text-slate-100">{{ __('Three things to get your first booking') }}</h2>

            <ol class="mt-4 space-y-4">
                @foreach ($nextSteps as $index => $item)
                    <li class="flex items-start gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">{{ $index + 1 }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $item['title'] }}</p>
                            <p class="mt-0.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ $item['body'] }}</p>
                        </div>
                        <a href="{{ $item['url'] }}"
                           class="shrink-0 rounded-xl border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 transition-colors hover:border-primary/40 hover:text-primary dark:border-slate-700 dark:text-slate-300">
                            {{ $item['cta'] }}
                        </a>
                    </li>
                @endforeach
            </ol>
        </section>

        <form method="POST" action="{{ route('teacher.welcome.live.ack') }}" class="text-center">
            @csrf
            <button type="submit"
                    class="inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-primary px-6 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 transition-colors hover:bg-primary/90">
                {{ __('Start teaching') }}
                <span aria-hidden="true">→</span>
            </button>
            <p class="mt-2 text-xs text-slate-400">{{ __('You can always find this checklist on your dashboard.') }}</p>
        </form>
    </div>
</x-app-layout>
