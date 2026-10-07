@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $chip = 'inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300';
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">
                {{ __('Lesson #:id', ['id' => str_pad((string) $booking->id, 6, '0', STR_PAD_LEFT)]) }}
            </h2>
            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $booking->status->badgeClasses() }}">{{ $booking->status->label() }}</span>
            @if ($booking->meeting_status)
                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $booking->meeting_status->badgeClasses() }}">{{ __('Classroom: :status', ['status' => $booking->meeting_status->label()]) }}</span>
            @endif
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6">
        <a href="{{ route('admin.bookings.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 transition-colors hover:text-primary dark:text-slate-400">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            {{ __('Back to bookings') }}
        </a>

        @if (session('status') === 'booking-cancelled')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                {{ __('Booking cancelled and both parties notified.') }}
            </div>
        @elseif (session('status') === 'booking-completed')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                {{ __('Lesson closed — the teacher can now be paid for it.') }}
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Parties -->
            <div class="{{ $cardBase }} lg:col-span-2" :class="{{ $cardTheme }}">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Parties') }}</h3>

                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-800/40">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Student') }}</p>
                        <a href="{{ route('admin.users.show', $booking->student) }}" class="mt-1 block font-semibold text-slate-800 hover:text-primary dark:text-slate-100">
                            {{ $booking->student->name }}
                        </a>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $booking->student->email }}</p>
                        @if ($booking->learner_name && $booking->learner_name !== $booking->student->name)
                            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ __('Learner: :name', ['name' => $booking->learner_name]) }}</p>
                        @endif
                    </div>

                    <div class="rounded-xl bg-slate-50 p-4 dark:bg-slate-800/40">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Teacher') }}</p>
                        <a href="{{ route('admin.users.show', $booking->teacherProfile->user) }}" class="mt-1 block font-semibold text-slate-800 hover:text-primary dark:text-slate-100">
                            {{ $booking->teacherProfile->user->name }}
                        </a>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $booking->teacherProfile->user->email }}</p>
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                            ★ {{ number_format((float) $booking->teacherProfile->rating_avg, 1) }}
                            · {{ $booking->teacherProfile->lessons_completed_count }} {{ __('lessons') }}
                        </p>
                    </div>
                </div>

                <dl class="mt-6 grid gap-5 sm:grid-cols-3">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('When') }}</dt>
                        <dd class="mt-1 text-sm text-slate-700 dark:text-slate-200">
                            {{ $booking->starts_at->copy()->setTimezone($timezone)->format('d M Y, H:i') }}
                            <p class="text-xs text-slate-400">{{ $booking->durationMinutes() }} {{ __('min') }}</p>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Subject') }}</dt>
                        <dd class="mt-1 text-sm text-slate-700 dark:text-slate-200">
                            {{ $booking->subject?->name ?? '—' }}
                            @if ($booking->lesson)
                                <p class="text-xs text-slate-400">{{ $booking->lesson->name }}</p>
                            @endif
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Origin') }}</dt>
                        <dd class="mt-1 text-sm text-slate-700 dark:text-slate-200">
                            @if ($booking->tutoringRequest)
                                {{ __('From request #:id', ['id' => $booking->tutoring_request_id]) }}
                            @else
                                {{ __('Direct booking') }}
                            @endif
                        </dd>
                    </div>
                </dl>

                @if ($booking->cancellation_reason)
                    <div class="mt-5 rounded-xl bg-slate-50 p-4 text-sm text-slate-700 dark:bg-slate-800/40 dark:text-slate-200">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Cancellation reason') }}</p>
                        <p class="mt-1">{{ $booking->cancellation_reason }}</p>
                    </div>
                @endif
            </div>

            <!-- Money -->
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Money') }}</h3>

                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500 dark:text-slate-400">{{ __('Price') }}</dt>
                        <dd class="font-semibold text-slate-800 dark:text-slate-100">{{ $money($booking->price_minor) }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500 dark:text-slate-400">{{ __('Platform fee') }}</dt>
                        <dd class="text-slate-700 dark:text-slate-200">{{ $money($booking->platform_fee_minor) }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500 dark:text-slate-400">{{ __('Teacher payout') }}</dt>
                        <dd class="text-slate-700 dark:text-slate-200">{{ $money($booking->teacher_payout_minor) }}</dd>
                    </div>
                    @if ($booking->booking_fee_minor > 0)
                        <div class="flex items-center justify-between">
                            <dt class="text-slate-500 dark:text-slate-400">{{ __('Student booking fee') }}</dt>
                            <dd class="text-slate-700 dark:text-slate-200">{{ $money($booking->booking_fee_minor) }}</dd>
                        </div>
                        @if ($booking->hasBookingFeeDiscount())
                            <div class="flex items-center justify-between">
                                <dt class="text-slate-500 dark:text-slate-400">
                                    {{ __('Special offer') }}
                                    @if ($booking->bookingFeePromotion)
                                        <span class="text-emerald-600 dark:text-emerald-400">· {{ $booking->bookingFeePromotion->name }}</span>
                                    @endif
                                </dt>
                                <dd class="font-semibold text-emerald-600 dark:text-emerald-400">−{{ $money($booking->booking_fee_discount_minor) }}</dd>
                            </div>
                        @endif
                        <div class="flex items-center justify-between border-t border-slate-100 pt-3 dark:border-slate-800">
                            <dt class="font-semibold text-slate-600 dark:text-slate-300">{{ __('Student pays') }}</dt>
                            <dd class="font-bold text-slate-900 dark:text-slate-100">{{ $money($booking->totalMinor()) }}</dd>
                        </div>
                    @endif
                </dl>

                @if ($payment)
                    <div class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-800">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Payment') }}</p>
                        <a href="{{ route('admin.payments.show', $payment) }}" class="mt-1 block text-sm font-semibold text-primary hover:underline">
                            {{ $money($payment->amount_minor) }} · {{ $payment->status->label() }}
                        </a>
                        <p class="text-xs text-slate-400">{{ $payment->gateway }} · {{ $payment->gateway_payment_id ?? $payment->gateway_order_id }}</p>
                    </div>
                @else
                    <p class="mt-4 border-t border-slate-100 pt-4 text-sm text-slate-500 dark:border-slate-800 dark:text-slate-400">
                        {{ __('No payment on this booking.') }}
                    </p>
                @endif

                @if ($booking->refunds->isNotEmpty())
                    <div class="mt-4 border-t border-slate-100 pt-4 dark:border-slate-800">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Refunds') }}</p>
                        <ul class="mt-2 space-y-1.5 text-sm">
                            @foreach ($booking->refunds as $refund)
                                <li class="flex items-center justify-between gap-2">
                                    <span class="text-slate-700 dark:text-slate-200">{{ $money($refund->amount_minor) }} ({{ $refund->percent }}%)</span>
                                    <span class="{{ $chip }}">{{ $refund->status->label() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Timeline -->
            <div class="{{ $cardBase }} lg:col-span-2" :class="{{ $cardTheme }}">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Timeline') }}</h3>

                <ol class="mt-4 space-y-4">
                    @foreach ($booking->timeline() as $step)
                        <li class="flex gap-3">
                            <div class="mt-1.5 h-2 w-2 shrink-0 rounded-full bg-primary"></div>
                            <div>
                                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $step['label'] }}</p>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    @if ($step['at'])
                                        {{ $step['at']->copy()->setTimezone($timezone)->format('d M Y, H:i') }} ·
                                    @endif
                                    {{ $step['description'] }}
                                </p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>

            <!-- Classroom & actions -->
            <div class="space-y-6">
                <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Classroom') }}</h3>
                    <p class="mt-2 text-sm text-slate-500 dark:text-slate-400">
                        {{ $booking->meeting_status?->label() ?? __('Not provisioned yet') }}
                        @if ($booking->meeting_error)
                            <span class="mt-1 block text-xs text-rose-500">{{ $booking->meeting_error }}</span>
                        @endif
                    </p>

                    @if ($booking->meeting_status === \App\Enums\MeetingStatus::Failed)
                        <form method="POST" action="{{ route('classroom.retry', $booking) }}" class="mt-4">
                            @csrf
                            <x-primary-button>{{ __('Regenerate link') }}</x-primary-button>
                        </form>
                    @endif
                </div>

                <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Support actions') }}</h3>

                    @if ($booking->status->isLive() || $booking->isHold())
                        <form method="POST" action="{{ route('admin.bookings.cancel', $booking) }}" class="mt-4 space-y-3">
                            @csrf
                            <div>
                                <x-input-label for="cancel-reason" :value="__('Cancellation reason')" />
                                <textarea id="cancel-reason" name="reason" rows="2" required
                                          class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">{{ old('reason') }}</textarea>
                                @error('reason')
                                    <p class="mt-1 text-xs text-rose-500">{{ $message }}</p>
                                @enderror
                            </div>
                            <button type="submit"
                                    class="inline-flex items-center rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-rose-700">
                                {{ __('Cancel booking') }}
                            </button>
                        </form>
                    @else
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ __('This lesson is closed — nothing left to cancel.') }}</p>
                    @endif

                    @if ($booking->status === \App\Enums\BookingStatus::Confirmed || $booking->status === \App\Enums\BookingStatus::InProgress)
                        <form method="POST" action="{{ route('admin.bookings.force-complete', $booking) }}" class="mt-4">
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition-colors hover:border-primary hover:text-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                {{ __('Force complete') }}
                            </button>
                        </form>
                    @endif
                </div>

                @if ($booking->review)
                    <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                        <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Review') }}</h3>
                        <p class="mt-2 text-sm text-slate-700 dark:text-slate-200">★ {{ $booking->review->rating }}</p>
                        <p class="text-xs text-slate-500 dark:text-slate-400">{{ $booking->review->comment ?: __('No comment') }}</p>
                        @if ($booking->review->hidden_at)
                            <p class="mt-2 text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Hidden by support') }}</p>
                        @elseif ($booking->review->flagged_at)
                            <p class="mt-2 text-xs font-semibold text-amber-600 dark:text-amber-400">{{ __('Reported: :reason', ['reason' => $booking->review->flagReasonLabel()]) }}</p>
                        @endif
                    </div>
                @endif

                @if ($booking->disputes->isNotEmpty())
                    <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                        <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Disputes') }}</h3>
                        <ul class="mt-2 space-y-2 text-sm">
                            @foreach ($booking->disputes as $dispute)
                                <li class="flex items-center justify-between gap-2">
                                    <a href="{{ route('admin.disputes.show', $dispute) }}" class="font-semibold text-primary hover:underline">{{ __('#:id', ['id' => $dispute->id]) }}</a>
                                    <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $dispute->status->badgeClasses() }}">{{ $dispute->status->label() }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                @endif
            </div>
        </div>

        @if ($booking->conversation)
            <!-- Chat evidence -->
            <div class="{{ $cardBase }} p-0" :class="{{ $cardTheme }}">
                <div class="flex items-center justify-between border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Lesson chat') }}</h3>
                    <span class="text-xs text-slate-400">{{ trans_choice('{0} No messages|{1} :count message|[2,*] :count messages', $booking->conversation->messages->count(), ['count' => $booking->conversation->messages->count()]) }}</span>
                </div>

                <ul class="max-h-96 space-y-3 overflow-y-auto px-6 py-4">
                    @forelse ($booking->conversation->messages as $message)
                        <li class="text-sm">
                            <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">
                                {{ $message->isSystem() ? __('Studylikepro') : ($message->sender?->name ?? __('Unknown')) }}
                                · {{ $message->created_at->copy()->setTimezone($timezone)->format('d M Y, H:i') }}
                            </p>
                            <p class="mt-0.5 whitespace-pre-line {{ $message->isSystem() ? 'italic text-slate-500 dark:text-slate-400' : 'text-slate-700 dark:text-slate-200' }}">
                                {{ $message->body }}
                            </p>
                            @if ($message->attachmentUrl() && $message->type === \App\Enums\MessageType::Image)
                                <a href="{{ $message->attachmentUrl() }}" target="_blank" class="text-xs font-semibold text-primary hover:underline">{{ __('View image') }}</a>
                            @endif
                        </li>
                    @empty
                        <li class="py-4 text-sm text-slate-500 dark:text-slate-400">{{ __('No messages on this thread.') }}</li>
                    @endforelse
                </ul>
            </div>
        @endif
    </div>
</x-app-layout>
