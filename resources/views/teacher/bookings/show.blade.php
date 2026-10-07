@php
    use App\Enums\BookingStatus;

    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);
    $learner = $booking->learner_name ?: $booking->student->name;
    $startLabel = $booking->starts_at->copy()->setTimezone($timezone)->format('D d M Y');
    $startTime = $booking->starts_at->copy()->setTimezone($timezone)->format('H:i');
    $endTime = $booking->ends_at->copy()->setTimezone($timezone)->format('H:i');
    $canStart = $booking->status === BookingStatus::Confirmed;
    $canComplete = $booking->status->isLive();
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <a href="{{ route('teacher.schedule.index') }}" class="text-xs font-semibold text-slate-400 transition-colors hover:text-primary">&larr; {{ __('My schedule') }}</a>
                <h2 class="mt-1 font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ $learner }}</h2>
            </div>
            <x-booking-status-badge :status="$booking->status" class="px-3 py-1 text-xs" />
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-6">
        @if (session('status') === 'booking-start')
            <div class="rounded-2xl border border-primary/30 bg-primary/5 p-4 text-sm text-primary">{{ __('Lesson started — the classroom opens for the student now.') }}</div>
        @elseif (session('status') === 'booking-complete')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                {{ __('Lesson marked as delivered.') }}
            </div>
        @elseif (session('status') === 'booking-markNoShow')
            <div class="rounded-2xl border border-rose-200/80 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
                {{ __('Marked as a no-show.') }}
            </div>
        @elseif (session('status') === 'booking-cancelled')
            <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-300">
                {{ __('Lesson cancelled. The student has been notified.') }}
            </div>
        @endif

        @if ($errors->has('status'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
                {{ $errors->first('status') }}
            </div>
        @endif

        <!-- Lesson -->
        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Lesson') }}</p>
            <p class="mt-1 text-lg font-bold text-slate-800 dark:text-slate-100">{{ $startLabel }}, {{ $startTime }}–{{ $endTime }}</p>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ $booking->durationMinutes() }} {{ __('minutes') }} · {{ $booking->subject?->name }}@if ($booking->lesson) · {{ $booking->lesson->name }}@endif
            </p>

            <dl class="mt-5 grid gap-4 border-t border-slate-200 pt-5 text-sm sm:grid-cols-3 dark:border-slate-800">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Learner') }}</dt>
                    <dd class="mt-1 font-semibold text-slate-700 dark:text-slate-200">{{ $learner }}</dd>
                    @if ($booking->learnerGrade)
                        <dd class="text-xs text-slate-500 dark:text-slate-400">{{ $booking->learnerGrade->label }}</dd>
                    @endif
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Account') }}</dt>
                    <dd class="mt-1 font-semibold text-slate-700 dark:text-slate-200">{{ $booking->student->name }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Reference') }}</dt>
                    <dd class="mt-1 font-semibold text-slate-700 dark:text-slate-200">#{{ str_pad((string) $booking->id, 6, '0', STR_PAD_LEFT) }}</dd>
                </div>
            </dl>

            <p class="mt-5 rounded-xl bg-slate-50 px-4 py-3 dark:bg-slate-800/60">
                <span class="flex flex-wrap items-center justify-between gap-3">
                    <span class="text-xs text-slate-500 dark:text-slate-400">
                        <span class="font-semibold uppercase tracking-wider text-slate-400">{{ __('Live classroom') }}</span><br>
                        @if ($booking->canJoinNow())
                            {{ __('Open now — your student can join.') }}
                        @elseif ($booking->meeting_status === \App\Enums\MeetingStatus::Failed)
                            {{ __('The room could not be opened. Try again from the classroom page.') }}
                        @elseif ($booking->status->isLive())
                            {{ __('Joins open :minutes minutes before the start time.', ['minutes' => $booking->joinOpensBeforeMinutes()]) }}
                        @else
                            {{ __('The classroom follows the lesson status.') }}
                        @endif
                    </span>
                    <span class="flex flex-wrap items-center justify-end gap-2">
                        @if ($booking->conversation)
                            <a href="{{ route('messages.show', $booking->conversation) }}"
                               class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition-colors hover:bg-white dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                                {{ __('Message :name', ['name' => $booking->learner_name ?: $booking->student->name]) }}
                            </a>
                        @endif
                        <a href="{{ route('classroom.show', $booking) }}"
                           class="inline-flex items-center gap-2 rounded-xl {{ $booking->canJoinNow() ? 'bg-primary px-4 py-2 text-sm font-bold text-white shadow-sm transition-colors hover:bg-primary/90' : 'border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition-colors hover:bg-white dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                            {{ $booking->canJoinNow() ? __('Join lesson') : __('Live classroom') }}
                        </a>
                    </span>
                </span>
            </p>
        </div>

        <!-- Money -->
        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('Earnings for this lesson') }}</h3>
            <div class="mt-3 space-y-2 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <span class="text-slate-500 dark:text-slate-400">{{ __('Lesson price') }}</span>
                    <span class="font-semibold text-slate-800 dark:text-slate-100">{{ $money($booking->price_minor) }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span class="text-slate-500 dark:text-slate-400">{{ __('Platform fee (:percent%)', ['percent' => $booking->commission_percent]) }}</span>
                    <span class="font-semibold text-slate-500 dark:text-slate-400">−{{ $money($booking->platform_fee_minor) }}</span>
                </div>
                <div class="flex items-center justify-between gap-3 border-t border-slate-200 pt-2 dark:border-slate-800">
                    <span class="font-semibold text-slate-600 dark:text-slate-300">{{ __('You earn') }}</span>
                    <span class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ $money($booking->teacher_payout_minor) }}</span>
                </div>
            </div>
            @if ($earning = $booking->earning)
                <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                    @if ($earning->reversed_minor > 0)
                        {{ __('This lesson was refunded, so :amount was reversed from your ledger.', ['amount' => $money($earning->reversed_minor)]) }}
                    @elseif ($earning->status === \App\Enums\EarningStatus::Paid)
                        {{ __('Paid out :date.', ['date' => $earning->paid_at?->format('d M Y') ?? '']) }}
                    @elseif ($earning->status === \App\Enums\EarningStatus::Eligible)
                        {{ __('Available for payout in your earnings ledger.') }}
                    @else
                        {{ __('Held until the lesson is delivered, then it becomes available for payout.') }}
                    @endif
                </p>
            @endif
        </div>

        <!-- Timeline -->
        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('Status timeline') }}</h3>
            <div class="mt-4">
                <x-booking-timeline :booking="$booking" :timezone="$timezone" />
            </div>
        </div>

        <!-- Actions -->
        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <div class="flex flex-wrap items-center gap-3">
                @if ($canStart)
                    <form method="POST" action="{{ route('teacher.bookings.start', $booking) }}">
                        @csrf
                        <x-primary-button>{{ __('Start lesson') }}</x-primary-button>
                    </form>
                @endif

                @if ($canComplete)
                    <form method="POST" action="{{ route('teacher.bookings.complete', $booking) }}">
                        @csrf
                        <x-primary-button>{{ __('Mark as delivered') }}</x-primary-button>
                    </form>
                @endif

                @if ($booking->status === BookingStatus::Confirmed)
                    <form method="POST" action="{{ route('teacher.bookings.no-show', $booking) }}">
                        @csrf
                        <x-secondary-button>{{ __('Student did not show') }}</x-secondary-button>
                    </form>
                @endif

                @unless ($booking->status->isFinal())
                    <button type="button" x-data="" @click="$dispatch('open-modal', 'cancel-booking')"
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                        {{ __('Cancel lesson') }}
                    </button>
                @endunless
            </div>

            @if ($canStart)
                <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                    {{ __('You can open the classroom from :minutes minutes before the start time.', ['minutes' => config('studylikepro.booking.teacher_start_early_minutes')]) }}
                </p>
            @endif
        </div>
    </div>

    @unless ($booking->status->isFinal())
        <x-modal name="cancel-booking" focusable>
            <form method="POST" action="{{ route('teacher.bookings.cancel', $booking) }}" class="p-6">
                @csrf
                <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ __('Cancel this lesson?') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('The student is notified straight away and the slot is released. Cancelling late counts against your reliability score.') }}
                </p>
                <div class="mt-4">
                    <x-input-label for="cancel-reason" :value="__('Reason (shared with the student)')" />
                    <textarea id="cancel-reason" name="reason" rows="3"
                              class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"></textarea>
                    <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                </div>
                <div class="mt-6 flex justify-end gap-3">
                    <x-secondary-button x-on:click="$dispatch('close')">{{ __('Keep lesson') }}</x-secondary-button>
                    <x-danger-button>{{ __('Cancel lesson') }}</x-danger-button>
                </div>
            </form>
        </x-modal>
    @endunless
</x-app-layout>