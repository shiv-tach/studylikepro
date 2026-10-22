@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $timezone = config('studylikepro.default_display_timezone');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('teacher.plans.index') }}" class="text-xs font-semibold text-slate-400 transition-colors hover:text-primary">&larr; {{ __('Plans & purchases') }}</a>
            <h2 class="mt-1 font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">
                {{ $purchase->type()->icon() }} {{ $purchase->scopeLabel() }}
            </h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ $purchase->student->name }} · {{ __('your payout') }} {{ platform_settings()->formatMinor($purchase->teacher_payout_minor) }}
            </p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Status') }}</p>
                    <span class="mt-1 inline-block rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $purchase->status->badgeClasses() }}">{{ $purchase->status->label() }}</span>
                    @if ($purchase->isLessonPlan() && $purchase->status->isConsumable() && $purchase->allocation_minutes)
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                            {{ __(':minutes minutes left for the student.', ['minutes' => $purchase->remainingMinutes()]) }}
                            @if ($purchase->expires_at) · {{ __('valid until :date', ['date' => $purchase->expires_at->copy()->setTimezone($timezone)->format('d M Y')]) }} @endif
                        </p>
                    @endif
                </div>
                <div class="text-right">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Commission') }}</p>
                    <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $purchase->commission_percent }}%</p>
                </div>
            </div>
        </div>

        @if ($purchase->sessions->isNotEmpty())
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('Sessions') }}</h3>
                <ul class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($purchase->sessions as $session)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                            <a href="{{ route('teacher.bookings.show', $session) }}" class="group flex-1">
                                <p class="text-sm font-semibold text-slate-700 group-hover:text-primary dark:text-slate-200">
                                    {{ $session->starts_at->copy()->setTimezone($timezone)->format('D, d M Y · H:i') }}
                                    · {{ $session->durationMinutes() }} {{ __('min') }}
                                </p>
                                <p class="text-xs text-slate-400">{{ __('Classroom and session controls open from the session page.') }}</p>
                            </a>
                            <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $session->status->badgeClasses() }}">{{ $session->status->label() }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @else
            <div class="{{ $cardBase }} px-6 py-8 text-center" :class="{{ $cardTheme }}">
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('No sessions booked yet.') }}</p>
            </div>
        @endif
    </div>
</x-app-layout>
