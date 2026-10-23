@php
    $themePreset = auth()->user()->theme['preset'] ?? 'classic';
    $cardClass = 'rounded-2xl border p-6 '.(in_array($themePreset, ['glass', 'ocean'], true)
        ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md'
        : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900');
    $isPlan = $purchase->isLessonPlan();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('student.purchases.show', $purchase) }}" class="text-xs font-semibold text-slate-400 transition-colors hover:text-primary">&larr; {{ $purchase->scopeLabel() }}</a>
            <h2 class="mt-1 font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Book a session') }}</h2>
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-6">
        @if ($errors->has('starts_at') || $errors->has('duration_minutes') || $errors->has('purchase'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
                {{ $errors->first('starts_at', $errors->first('duration_minutes', $errors->first('purchase'))) }}
            </div>
        @endif

        <div class="{{ $cardClass }}">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('1. Session length') }}</h3>
                    @if ($isPlan)
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ __(':minutes minutes left on this plan — split it however you like.', ['minutes' => $purchase->remainingMinutes()]) }}
                        </p>
                    @endif
                </div>
                <form method="GET" action="{{ route('student.purchase-sessions.create', $purchase) }}" class="flex flex-wrap gap-2">
                    @foreach ($durations as $option)
                        <button type="submit" name="duration_minutes" value="{{ $option }}"
                                class="rounded-xl border px-3 py-2 text-sm font-semibold transition-colors {{ $option === $duration ? 'border-primary bg-primary/10 text-primary' : 'border-slate-200 text-slate-600 hover:border-primary hover:text-primary dark:border-slate-700 dark:text-slate-300' }}">
                            {{ $option }} {{ __('min') }}
                        </button>
                    @endforeach
                </form>
            </div>

            <h3 class="mt-6 border-t border-slate-200 pt-5 text-sm font-bold text-slate-800 dark:border-slate-800 dark:text-slate-100">{{ __('2. Pick a time') }}</h3>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                {{ __('All times are shown in :timezone. The session is already paid for from your purchase.', ['timezone' => $timezone]) }}
            </p>

            @if ($slots === [])
                <p class="mt-4 rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400">
                    {{ __('No open :minutes-minute slots — try another session length.', ['minutes' => $duration]) }}
                </p>
            @else
                <form method="POST" action="{{ route('student.purchase-sessions.store', $purchase) }}">
                    @csrf
                    <input type="hidden" name="duration_minutes" value="{{ $duration }}">

                    <div class="mt-4 space-y-5">
                        @foreach ($slots as $date => $dateSlots)
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ \Carbon\CarbonImmutable::parse($date)->format('D, d M') }}</p>
                                <div class="mt-2 flex flex-wrap gap-2">
                                    @foreach ($dateSlots as $slot)
                                        <label class="cursor-pointer">
                                            <input type="radio" name="starts_at" value="{{ $slot['starts_at']->toIso8601String() }}"
                                                   class="peer sr-only" required>
                                            <span class="inline-flex items-center rounded-xl border border-slate-200 px-3 py-2 text-sm font-semibold text-slate-600 transition-colors peer-checked:border-primary peer-checked:bg-primary/10 peer-checked:text-primary peer-focus-visible:ring-2 peer-focus-visible:ring-primary/40 dark:border-slate-700 dark:text-slate-300">
                                                {{ $slot['label'] }}
                                            </span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-6 border-t border-slate-200 pt-5 dark:border-slate-800">
                        <button type="submit"
                                class="inline-flex items-center rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary/90">
                            {{ __('Confirm session') }}
                        </button>
                        <p class="mt-2 text-xs text-slate-400">{{ __('No payment needed — this session is covered by your purchase.') }}</p>
                    </div>
                </form>
            @endif
        </div>
    </div>
</x-app-layout>
