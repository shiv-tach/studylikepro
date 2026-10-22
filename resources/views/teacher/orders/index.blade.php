@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Plans & purchases') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Everything students have bought from your service catalog.') }}</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-5xl space-y-6">
        <form method="GET" action="{{ route('teacher.plans.index') }}" class="flex flex-wrap items-center gap-2">
            <select name="status" onchange="this.form.submit()"
                    class="rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                <option value="">{{ __('All statuses') }}</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected($selectedStatus === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
        </form>

        @if ($purchases->isEmpty())
            <div class="{{ $cardBase }} px-6 py-12 text-center" :class="{{ $cardTheme }}">
                <p class="text-4xl">📭</p>
                <p class="mt-3 text-sm font-semibold text-slate-600 dark:text-slate-300">{{ __('No purchases yet.') }}</p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Publish services from the Services & pricing page to start selling.') }}</p>
            </div>
        @else
            <div class="space-y-3">
                @foreach ($purchases as $purchase)
                    <a href="{{ route('teacher.plans.show', $purchase) }}"
                       class="{{ $cardBase }} block transition-all hover:-translate-y-0.5 hover:shadow-md" :class="{{ $cardTheme }}">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-wider text-primary">{{ $purchase->type()->icon() }} {{ $purchase->type()->label() }}</p>
                                <p class="mt-1 font-bold text-slate-800 dark:text-slate-100">{{ $purchase->scopeLabel() }}</p>
                                <p class="mt-0.5 text-sm text-slate-500 dark:text-slate-400">
                                    {{ $purchase->student->name }}
                                    · {{ platform_settings()->formatMinor($purchase->teacher_payout_minor) }} {{ __('payout') }}
                                </p>
                            </div>
                            <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $purchase->status->badgeClasses() }}">{{ $purchase->status->label() }}</span>
                        </div>
                    </a>
                @endforeach
            </div>

            <div class="mt-6">{{ $purchases->links() }}</div>
        @endif
    </div>
</x-app-layout>
