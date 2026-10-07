@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $fieldClasses = 'rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);

    $stats = [
        ['label' => __('Live lessons'), 'value' => $stats['live'], 'tone' => 'text-emerald-600 dark:text-emerald-400'],
        ['label' => __('Unpaid holds'), 'value' => $stats['holds'], 'tone' => 'text-amber-600 dark:text-amber-400'],
        ['label' => __('Completed'), 'value' => $stats['completed'], 'tone' => 'text-slate-700 dark:text-slate-200'],
        ['label' => __('Cancelled / expired'), 'value' => $stats['cancelled'], 'tone' => 'text-slate-500 dark:text-slate-400'],
        ['label' => __('Classroom issues'), 'value' => $stats['meeting_failed'], 'tone' => $stats['meeting_failed'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-500 dark:text-slate-400'],
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Bookings') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Every lesson hold and booking on the platform.') }}</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6">
        @if (session('status') === 'booking-cancelled')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                {{ __('Booking cancelled and both parties notified.') }}
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            @foreach ($stats as $stat)
                <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $stat['label'] }}</p>
                    <p class="mt-2 text-2xl font-extrabold tracking-tight {{ $stat['tone'] }}">{{ $stat['value'] }}</p>
                </div>
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.bookings.index') }}" class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <div class="grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
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
                    <x-input-label for="filter-teacher" :value="__('Teacher')" />
                    <select id="filter-teacher" name="teacher" class="mt-1 block w-full {{ $fieldClasses }}">
                        <option value="">{{ __('Any teacher') }}</option>
                        @foreach ($teachers as $teacher)
                            <option value="{{ $teacher->id }}" @selected(($filters['teacher'] ?? null) == $teacher->id)>{{ $teacher->user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="filter-search" :value="__('Student or learner')" />
                    <x-text-input id="filter-search" name="search" type="text" class="mt-1 block w-full"
                                  :value="$filters['search'] ?? null" placeholder="{{ __('Name or email') }}" />
                </div>
                <div>
                    <x-input-label for="filter-from" :value="__('From')" />
                    <x-text-input id="filter-from" name="from" type="date" class="mt-1 block w-full" :value="$filters['from'] ?? null" />
                </div>
                <div>
                    <x-input-label for="filter-to" :value="__('To')" />
                    <x-text-input id="filter-to" name="to" type="date" class="mt-1 block w-full" :value="$filters['to'] ?? null" />
                </div>
                <div class="flex items-end gap-3">
                    <x-primary-button>{{ __('Filter') }}</x-primary-button>
                    <a href="{{ route('admin.bookings.index') }}" class="text-sm font-semibold text-slate-500 hover:text-primary">{{ __('Reset') }}</a>
                </div>
            </div>
        </form>

        <div class="{{ $cardBase }} p-0" :class="{{ $cardTheme }}">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-400">
                            <th class="px-6 py-3">{{ __('Lesson') }}</th>
                            <th class="px-6 py-3">{{ __('Parties') }}</th>
                            <th class="px-6 py-3">{{ __('When') }}</th>
                            <th class="px-6 py-3">{{ __('Money') }}</th>
                            <th class="px-6 py-3">{{ __('Status') }}</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                        @forelse ($bookings as $booking)
                            <tr>
                                <td class="px-6 py-4">
                                    <p class="font-semibold text-slate-800 dark:text-slate-100">#{{ str_pad((string) $booking->id, 6, '0', STR_PAD_LEFT) }}</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        {{ $booking->subject?->name }}@if ($booking->lesson) · {{ $booking->lesson->name }}@endif
                                    </p>
                                    @if ($booking->tutoring_request_id)
                                        <p class="text-xs text-slate-400">{{ __('From request #:id', ['id' => $booking->tutoring_request_id]) }}</p>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <p class="font-semibold text-slate-800 dark:text-slate-100">{{ $booking->student->name }}</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $booking->teacherProfile->user->name }}</p>
                                    @if ($booking->learner_name && $booking->learner_name !== $booking->student->name)
                                        <p class="text-xs text-slate-400">{{ __('Learner: :name', ['name' => $booking->learner_name]) }}</p>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-slate-700 dark:text-slate-200">{{ $booking->starts_at->copy()->setTimezone($timezone)->format('d M Y, H:i') }}</p>
                                    <p class="text-xs text-slate-400">{{ $booking->durationMinutes() }} {{ __('min') }}</p>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="font-semibold text-slate-800 dark:text-slate-100">{{ $money($booking->price_minor) }}</p>
                                    <p class="text-xs text-slate-400">{{ __('Fee :fee · payout :payout', [
                                        'fee' => $money($booking->platform_fee_minor),
                                        'payout' => $money($booking->teacher_payout_minor),
                                    ]) }}</p>
                                </td>
                                <td class="px-6 py-4">
                                    <x-booking-status-badge :status="$booking->status" />
                                    @if ($booking->cancelled_by)
                                        <p class="mt-1 text-xs text-slate-400">{{ __('by :who', ['who' => $booking->cancelled_by]) }}</p>
                                    @endif
                                    @if ($booking->meeting_status)
                                        <p class="mt-1">
                                            <span class="rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $booking->meeting_status->badgeClasses() }}">
                                                {{ __('Classroom: :status', ['status' => $booking->meeting_status->label()]) }}
                                            </span>
                                        </p>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('admin.bookings.show', $booking) }}" class="text-sm font-semibold text-primary hover:underline">
                                        {{ __('Details') }}
                                    </a>
                                    @if ($booking->status->isLive() && ! $booking->meetingIsReady())
                                        <form method="POST" action="{{ route('classroom.retry', $booking) }}" class="mt-2">
                                            @csrf
                                            <button type="submit" class="text-xs font-semibold text-primary hover:underline">
                                                {{ __('Regenerate link') }}
                                            </button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400">
                                    {{ __('No bookings match these filters yet.') }}
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($bookings->hasPages())
                <div class="border-t border-slate-200 px-6 py-4 dark:border-slate-800">{{ $bookings->links() }}</div>
            @endif
        </div>
    </div>
</x-app-layout>