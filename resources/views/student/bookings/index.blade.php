@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $fieldClasses = 'rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';
    $timezone = auth()->user()->studentProfile?->timezone ?? config('studylikepro.default_display_timezone');

    $tabs = [
        'upcoming' => __('Upcoming').' ('.$counts['upcoming'].')',
        'past' => __('Past').' ('.$counts['past'].')',
    ];

    $activeFilters = collect($filters)->except('tab')->filter()->isNotEmpty();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('My lessons') }}</h2>
            <a href="{{ route('student.teachers.index') }}"
               class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary/90">
                {{ __('Find a teacher') }}
            </a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        @if (session('status') === 'booking-reschedule')
            <div class="rounded-2xl border border-primary/30 bg-primary/5 p-4 text-sm text-primary">
                {{ __('Previous lesson released. Pick a new time below.') }}
            </div>
        @endif

        <div class="flex gap-2">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('student.bookings.index', array_merge(request()->query(), ['tab' => $key])) }}"
                   class="rounded-xl px-4 py-2 text-sm font-semibold transition-colors {{ $tab === $key
                        ? 'bg-primary text-white shadow-sm'
                        : 'border border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-800 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <!-- History filters -->
        <form method="GET" action="{{ route('student.bookings.index') }}" class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <input type="hidden" name="tab" value="{{ $tab }}">

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div>
                    <x-input-label for="filter-status" :value="__('Status')" />
                    <select id="filter-status" name="status" class="mt-1 block w-full {{ $fieldClasses }}">
                        <option value="">{{ __('Any status') }}</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="filter-subject" :value="__('Subject')" />
                    <select id="filter-subject" name="subject" class="mt-1 block w-full {{ $fieldClasses }}">
                        <option value="">{{ __('Any subject') }}</option>
                        @foreach ($subjects as $subject)
                            <option value="{{ $subject->id }}" @selected(($filters['subject'] ?? null) == $subject->id)>{{ $subject->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="filter-teacher" :value="__('Teacher')" />
                    <select id="filter-teacher" name="teacher" class="mt-1 block w-full {{ $fieldClasses }}">
                        <option value="">{{ __('Any teacher') }}</option>
                        @foreach ($teachers as $teacher)
                            <option value="{{ $teacher->id }}" @selected(($filters['teacher'] ?? null) == $teacher->id)>{{ $teacher->user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="filter-from" :value="__('From')" />
                    <input type="date" id="filter-from" name="from" value="{{ $filters['from'] ?? '' }}" class="mt-1 block w-full {{ $fieldClasses }}">
                </div>
                <div>
                    <x-input-label for="filter-to" :value="__('To')" />
                    <input type="date" id="filter-to" name="to" value="{{ $filters['to'] ?? '' }}" class="mt-1 block w-full {{ $fieldClasses }}">
                </div>
            </div>

            <div class="mt-4 flex flex-wrap items-center gap-3">
                <x-primary-button>{{ __('Filter') }}</x-primary-button>
                @if ($activeFilters)
                    <a href="{{ route('student.bookings.index', ['tab' => $tab]) }}" class="text-sm font-semibold text-slate-500 hover:text-primary">{{ __('Reset') }}</a>
                    <span class="text-xs text-slate-400">{{ trans_choice(':count lesson matches these filters|:count lessons match these filters', $bookings->total()) }}</span>
                @endif
            </div>
        </form>

        @forelse ($bookings as $booking)
            <x-booking-card :booking="$booking" :timezone="$timezone" perspective="student"
                            :href="route('student.bookings.show', $booking)"
                            :class="$cardBase.' '.$cardTheme" />
        @empty
            <div class="{{ $cardBase }} text-center" :class="{{ $cardTheme }}">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 text-2xl">{{ $tab === 'upcoming' ? '📅' : '🗂️' }}</span>
                <h3 class="mt-4 text-base font-bold text-slate-800 dark:text-slate-100">
                    {{ $tab === 'upcoming' ? __('No lessons booked yet') : __('Nothing in your history yet') }}
                </h3>
                <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                    {{ $tab === 'upcoming'
                        ? __('Pick a verified teacher, choose one of their open slots, and your lesson appears here the moment you book it.')
                        : __('Completed, cancelled and expired lessons will show up here.') }}
                </p>
                @if ($tab === 'upcoming')
                    <a href="{{ route('student.teachers.index') }}"
                       class="mt-5 inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary/90">
                        {{ __('Browse teachers') }}
                    </a>
                @endif
            </div>
        @endforelse

        @if ($bookings->hasPages())
            <div>{{ $bookings->links() }}</div>
        @endif
    </div>
</x-app-layout>
