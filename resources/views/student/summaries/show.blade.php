@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);
    $summary = $purchase->summary;
    $teacherUser = $purchase->teacherProfile?->user;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <a href="{{ route('student.purchases.index') }}" class="text-xs font-semibold text-slate-400 transition-colors hover:text-primary">&larr; {{ __('My purchases') }}</a>
                <h2 class="mt-1 font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">
                    {{ __('Lesson summary') }}
                </h2>
            </div>
            <x-booking-status-badge :status="$purchase->status" class="px-3 py-1 text-xs" />
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-6">
        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ $purchase->scopeLabel() }}</h3>
            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                {{ $purchase->type()->label() }} · {{ __('bought :when', ['when' => $purchase->paid_at?->format('d M Y') ?? $purchase->created_at->format('d M Y')]) }}
            </p>

            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex items-start justify-between gap-4">
                    <dt class="text-slate-500 dark:text-slate-400">{{ __('Teacher') }}</dt>
                    <dd class="font-semibold text-slate-800 dark:text-slate-100">{{ $teacherUser?->name ?? '—' }}</dd>
                </div>
                <div class="flex items-start justify-between gap-4">
                    <dt class="text-slate-500 dark:text-slate-400">{{ __('Lesson') }}</dt>
                    <dd class="font-semibold text-slate-800 dark:text-slate-100">
                        {{ $purchase->lesson?->name ?? $purchase->subject?->name ?? '—' }}
                    </dd>
                </div>
                <div class="flex items-start justify-between gap-4">
                    <dt class="text-slate-500 dark:text-slate-400">{{ __('Price') }}</dt>
                    <dd class="font-semibold text-slate-800 dark:text-slate-100">{{ $money($purchase->price_minor) }}</dd>
                </div>
            </dl>
        </div>

        @if ($summary?->isDelivered())
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('Your summary') }}</h3>
                    <span class="rounded-full bg-emerald-100 px-3 py-1 text-xs font-semibold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                        {{ __('Delivered :when', ['when' => $summary->delivered_at?->format('d M Y, H:i')]) }}
                    </span>
                </div>
                <div class="mt-4 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
                    {!! nl2br(e($summary->content)) !!}
                </div>
            </div>
        @else
            <div class="{{ $cardBase }} text-center" :class="{{ $cardTheme }}">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 text-2xl">📝</span>
                <h3 class="mt-4 text-base font-bold text-slate-800 dark:text-slate-100">{{ __('Awaiting delivery') }}</h3>
                <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                    {{ __('Your teacher is writing your personalised summary. It will appear here as soon as it is delivered.') }}
                </p>
            </div>
        @endif
    </div>
</x-app-layout>
