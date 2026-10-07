@php
    use App\Enums\BookingStatus;

    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('My schedule') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Times shown in :timezone.', ['timezone' => $timezone]) }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('teacher.requests.index') }}"
                   class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    {{ __('Question requests') }}
                </a>
                <a href="{{ route('teacher.availability.index') }}"
                   class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary/90">
                    {{ __('Edit availability') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        <!-- Awaiting payment -->
        @if ($holds->isNotEmpty())
            <div>
                <h3 class="mb-3 text-sm font-bold uppercase tracking-wider text-slate-400">{{ __('Awaiting payment') }}</h3>
                <div class="space-y-3">
                    @foreach ($holds as $booking)
                        <x-booking-card :booking="$booking" :timezone="$timezone" perspective="teacher"
                                        :href="route('teacher.bookings.show', $booking)"
                                        :class="$cardBase.' '.$cardTheme" />
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Upcoming lessons -->
        <div>
            <h3 class="mb-3 text-sm font-bold uppercase tracking-wider text-slate-400">{{ __('Upcoming lessons') }}</h3>

            @forelse ($upcoming as $booking)
                <x-booking-card :booking="$booking" :timezone="$timezone" perspective="teacher"
                                :href="route('teacher.bookings.show', $booking)"
                                :class="$cardBase.' '.$cardTheme.' mb-3'" />
            @empty
                <div class="{{ $cardBase }} text-center" :class="{{ $cardTheme }}">
                    <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 text-2xl">🗓️</span>
                    <h3 class="mt-4 text-base font-bold text-slate-800 dark:text-slate-100">{{ __('No lessons booked yet') }}</h3>
                    <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                        {{ __('Keep your availability and lessons up to date — students book directly into the open slots you publish.') }}
                    </p>
                    <a href="{{ route('teacher.availability.index') }}"
                       class="mt-5 inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary/90">
                        {{ __('Open availability') }}
                    </a>
                </div>
            @endforelse
        </div>

        <!-- History -->
        @if ($past->isNotEmpty())
            <div>
                <h3 class="mb-3 text-sm font-bold uppercase tracking-wider text-slate-400">{{ __('Recent history') }}</h3>
                <div class="{{ $cardBase }} divide-y divide-slate-200 p-0 dark:divide-slate-800" :class="{{ $cardTheme }}">
                    @foreach ($past as $booking)
                        <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <x-booking-status-badge :status="$booking->status" />
                                    <span class="text-xs text-slate-400">{{ $booking->starts_at->copy()->setTimezone($timezone)->format('D d M Y, H:i') }}</span>
                                </div>
                                <p class="mt-1 truncate text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    {{ $booking->learner_name ?: $booking->student->name }}
                                    · {{ $booking->subject?->name }}
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $money($booking->teacher_payout_minor) }}</p>
                                <a href="{{ route('teacher.bookings.show', $booking) }}" class="text-xs font-semibold text-primary hover:underline">{{ __('View') }}</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>
</x-app-layout>