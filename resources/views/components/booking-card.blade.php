@props(['booking', 'timezone', 'perspective' => 'student', 'href' => null])

@php
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);

    $title = collect([$booking->subject?->name, $booking->lesson?->name])->filter()->implode(' · ')
        ?: ($booking->tutoringRequest?->description ? Str::limit($booking->tutoringRequest->description, 60) : __('Tutoring lesson'));

    $counterpart = match ($perspective) {
        'teacher' => $booking->learner_name ?: $booking->student->name,
        'admin' => $booking->student->name.' → '.$booking->teacherProfile->user->name,
        default => $booking->teacherProfile->user->name,
    };

    $subtitle = match ($perspective) {
        'teacher' => trim(($booking->learner_name ?: $booking->student->name).($booking->learnerGrade ? ' · '.$booking->learnerGrade->label : '')),
        'admin' => $booking->learner_name ? __('Learner: :name', ['name' => $booking->learner_name]) : __('Student booking'),
        default => __('with :name', ['name' => $booking->teacherProfile->user->name]),
    };

    $amount = match ($perspective) {
        'teacher' => $booking->teacher_payout_minor,
        'admin' => $booking->price_minor,
        default => $booking->totalMinor(),
    };
    $amountLabel = $perspective === 'teacher' ? __('You earn') : ($perspective === 'admin' ? __('Price') : __('Total'));
@endphp

<a href="{{ $href }}" class="block transition-colors hover:border-primary/40 {{ $attributes->get('class') }}">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <x-booking-status-badge :status="$booking->status" />
                @if ($booking->isHold() && $booking->expires_at?->isFuture())
                    <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                        {{ __('Pay within :time', ['time' => $booking->expires_at->diffForHumans()]) }}
                    </span>
                @endif
                <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                    {{ $booking->durationMinutes() }} min
                </span>
                @if ($booking->canJoinNow())
                    <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                        {{ __('Join now') }}
                    </span>
                @endif
                @if ($perspective === 'student' && $booking->status === \App\Enums\BookingStatus::Completed && $booking->review === null)
                    <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                        {{ __('Review this lesson') }}
                    </span>
                @endif
            </div>

            <h3 class="mt-3 truncate text-sm font-bold text-slate-800 dark:text-slate-100">{{ $title }}</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $subtitle }}</p>
        </div>

        <div class="text-right">
            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                {{ $booking->starts_at->copy()->setTimezone($timezone)->format('D d M, H:i') }}–{{ $booking->ends_at->copy()->setTimezone($timezone)->format('H:i') }}
            </p>
            <p class="mt-1 text-xs text-slate-400">{{ $amountLabel }} {{ $money($amount) }}</p>
        </div>
    </div>
</a>