@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Lesson summaries') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Paid summary requests waiting for your write-up — delivering one completes the purchase.') }}
                </p>
            </div>
            <a href="{{ route('teacher.dashboard') }}"
               class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                {{ __('Back to dashboard') }}
            </a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        @if (session('status') === 'summary-delivered')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                {{ __('Summary delivered — the purchase is complete and your earning is now eligible for payout.') }}
            </div>
        @elseif (session('status') === 'summary-saved')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                {{ __('Draft saved.') }}
            </div>
        @endif

        @forelse ($summaries as $summary)
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-slate-800 dark:text-slate-100">
                            {{ $summary->purchase->student?->name ?? __('Student') }}
                        </p>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                            {{ $summary->purchase->lesson?->name ?? $summary->purchase->subject?->name ?? __('Summary service') }}
                            @if ($summary->purchase->lesson && $summary->purchase->subject)
                                · {{ $summary->purchase->subject->name }}
                            @endif
                            · {{ __('requested :when', ['when' => $summary->purchase->created_at->format('d M Y')]) }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        <x-booking-status-badge :status="$summary->purchase->status" />
                        <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $summary->isDelivered() ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400' }}">
                            {{ $summary->isDelivered() ? __('Delivered') : __('Draft') }}
                        </span>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    <a href="{{ route('teacher.summaries.edit', $summary) }}"
                       class="inline-flex items-center rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary/90">
                        {{ $summary->isDelivered() ? __('Edit') : __('Write') }}
                    </a>

                    @if ($summary->isDraft() && $summary->purchase->isActive())
                        <form method="POST" action="{{ route('teacher.summaries.deliver', $summary) }}">
                            @csrf
                            <x-secondary-button type="submit">{{ __('Deliver') }}</x-secondary-button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <div class="{{ $cardBase }} text-center" :class="{{ $cardTheme }}">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 text-2xl">📝</span>
                <h3 class="mt-4 text-base font-bold text-slate-800 dark:text-slate-100">{{ __('No summary requests yet') }}</h3>
                <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                    {{ __('When a student buys a lesson summary from you, it will show up here to write and deliver.') }}
                </p>
            </div>
        @endforelse
    </div>
</x-app-layout>
