@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $purchase = $summary->purchase;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <a href="{{ route('teacher.summaries.index') }}" class="text-xs font-semibold text-slate-400 transition-colors hover:text-primary">&larr; {{ __('Lesson summaries') }}</a>
                <h2 class="mt-1 font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">
                    {{ $summary->isDelivered() ? __('Summary delivered') : __('Write the summary') }}
                </h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ $purchase->lesson?->name ?? $purchase->subject?->name ?? $purchase->scopeLabel() }}
                    · {{ __('for :student', ['student' => $purchase->student?->name ?? __('a student')]) }}
                </p>
            </div>
            <x-booking-status-badge :status="$purchase->status" class="px-3 py-1 text-xs" />
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-6">
        @if (session('status') === 'summary-saved')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                {{ __('Draft saved.') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
                {{ __('Please fix the errors below and save again.') }}
            </div>
        @endif

        @if ($summary->isDelivered())
            <div class="{{ $cardBase }} border-emerald-200/80 bg-emerald-50/60 dark:border-emerald-900/40 dark:bg-emerald-950/20">
                <div class="flex items-start gap-3">
                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-xl dark:bg-emerald-900/40">✅</span>
                    <div>
                        <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ __('Delivered') }}</h3>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                            {{ __('This summary was delivered on :when and the purchase is complete.', [
                                'when' => $summary->delivered_at?->format('d M Y, H:i'),
                            ]) }}
                        </p>
                    </div>
                </div>
            </div>
        @endif

        <form method="POST" action="{{ route('teacher.summaries.update', $summary) }}" class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            @csrf
            @method('PUT')

            <div>
                <x-input-label for="content" :value="__('Your personalised summary')" />
                <textarea id="content" name="content" rows="14" maxlength="20000"
                          class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                          placeholder="{{ __('Write the summary of the lesson for your student…') }}">{{ old('content', $summary->content) }}</textarea>
                <x-input-error :messages="$errors->get('content')" class="mt-2" />
                <p class="mt-1 text-xs text-slate-400">{{ __('At least 20 characters; up to 20,000.') }}</p>
            </div>

            <div class="mt-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    @if ($summary->isDraft() && $purchase->isActive())
                        <button type="submit" form="deliver-summary-{{ $summary->id }}"
                                class="inline-flex items-center rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-bold text-white shadow-sm transition-colors hover:bg-emerald-700">
                            {{ __('Deliver summary') }}
                        </button>
                    @endif
                </div>
                <div class="flex items-center gap-3">
                    <a href="{{ route('teacher.summaries.index') }}"
                       class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                        {{ __('Back') }}
                    </a>
                    <x-primary-button>{{ __('Save draft') }}</x-primary-button>
                </div>
            </div>
        </form>

        @if ($summary->isDraft() && $purchase->isActive())
            <form id="deliver-summary-{{ $summary->id }}" method="POST" action="{{ route('teacher.summaries.deliver', $summary) }}" class="hidden">
                @csrf
            </form>
        @endif
    </div>
</x-app-layout>
