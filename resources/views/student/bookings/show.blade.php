@php
    use App\Enums\BookingStatus;

    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);
    $teacherUser = $booking->teacherProfile->user;
    $isHold = $booking->status === BookingStatus::PendingPayment;
    $holdLapsed = $isHold && $booking->expires_at?->isPast();
    $startLabel = $booking->starts_at->copy()->setTimezone($timezone)->format('D d M Y');
    $endLabel = $booking->ends_at->copy()->setTimezone($timezone)->format('H:i');
    $startTime = $booking->starts_at->copy()->setTimezone($timezone)->format('H:i');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <a href="{{ route('student.bookings.index') }}" class="text-xs font-semibold text-slate-400 transition-colors hover:text-primary">&larr; {{ __('My lessons') }}</a>
                <h2 class="mt-1 font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">
                    {{ $booking->lesson?->name ?? $booking->subject?->name ?? __('Lesson') }}
                </h2>
            </div>
            <x-booking-status-badge :status="$booking->status" class="px-3 py-1 text-xs" />
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-6">
        @if (session('status') === 'hold-created')
            <div class="rounded-2xl border border-amber-200/80 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300">
                {{ __('Slot reserved. Complete the payment before the hold lapses and the lesson is confirmed.') }}
            </div>
        @elseif (session('status') === 'booking-confirmed')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                {{ __('Payment received — your lesson is confirmed.') }}
            </div>
        @elseif (session('status') === 'booking-cancelled')
            <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-300">
                {{ __('Lesson cancelled. The slot is back on the teacher calendar.') }}
            </div>
        @elseif (session('status') === 'review-submitted')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                {{ __('Thank you! Your review is live on the teacher profile.') }}
            </div>
        @elseif (session('status') === 'review-updated')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                {{ __('Review updated — the teacher profile reflects your new rating.') }}
            </div>
        @elseif (session('status') === 'review-locked')
            <div class="rounded-2xl border border-amber-200/80 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300">
                {{ __('The edit window for this review has closed.') }}
            </div>
        @endif

        @if ($errors->has('status'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
                {{ $errors->first('status') }}
            </div>
        @endif

        @if ($booking->canJoinNow())
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50/70 p-5 dark:border-emerald-900/40 dark:bg-emerald-950/20">
                <div class="flex flex-wrap items-center justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-xl dark:bg-emerald-900/40">🎥</span>
                        <div>
                            <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ __('Your classroom is open') }}</h3>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                                {{ __('Join :teacher now — the room stays open until :time.', [
                                    'teacher' => $teacherUser->name,
                                    'time' => $booking->joinClosesAt()->copy()->setTimezone($timezone)->format('H:i'),
                                ]) }}
                            </p>
                        </div>
                    </div>
                    <a href="{{ route('classroom.show', $booking) }}"
                       class="inline-flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white shadow-sm transition-colors hover:bg-primary/90">
                        {{ __('Join lesson') }} &nearr;
                    </a>
                </div>
            </div>
        @endif

        <!-- Payment / status banner -->
        @if ($isHold && ! $holdLapsed)
            <div class="{{ $cardBase }} border-amber-200/80 bg-amber-50/60 dark:border-amber-900/40 dark:bg-amber-950/20">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="flex items-start gap-3">
                        <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-xl dark:bg-amber-900/40">⏳</span>
                        <div>
                            <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ __('Your slot is held') }}</h3>
                            <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                                {{ $startLabel }}, {{ $startTime }}–{{ $endLabel }} · {{ $booking->durationMinutes() }} {{ __('minutes') }}
                            </p>
                            <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                @if ($booking->expires_at)
                                    {{ __('Reserved until :time (:relative). After that the slot is released to other students.', [
                                        'time' => $booking->expires_at->copy()->setTimezone($timezone)->format('H:i'),
                                        'relative' => $booking->expires_at->diffForHumans(),
                                    ]) }}
                                @else
                                    {{ __('Complete the payment to confirm this lesson.') }}
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="text-right">
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            {{ __('Lesson :price', ['price' => $money($booking->price_minor)]) }}
                            @if ($booking->booking_fee_minor > 0)
                                · {{ __('booking fee :fee', ['fee' => $money($booking->netBookingFeeMinor())]) }}
                            @endif
                        </p>
                        <p class="mt-1 text-lg font-bold text-slate-800 dark:text-slate-100">{{ $money($booking->totalMinor()) }}</p>
                        <form method="POST" action="{{ route('student.bookings.checkout', $booking) }}" class="mt-2">
                            @csrf
                            <x-primary-button>{{ __('Pay :amount', ['amount' => $money($booking->totalMinor())]) }}</x-primary-button>
                        </form>
                        <p class="mt-2 text-[11px] text-amber-700 dark:text-amber-400">
                            {{ __('Secure checkout · the slot stays yours until :time', ['time' => $booking->expires_at?->copy()->setTimezone($timezone)->format('H:i') ?? __('you pay')]) }}
                        </p>
                    </div>
                </div>
            </div>
        @elseif ($holdLapsed || $booking->status === BookingStatus::Expired)
            <div class="{{ $cardBase }} border-slate-200/80 bg-slate-50 dark:border-slate-800 dark:bg-slate-900/60">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ __('This hold lapsed') }}</h3>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ __('The payment window closed and the slot went back on the teacher calendar.') }}</p>
                <a href="{{ route('student.bookings.create', $booking->teacher_profile_id) }}"
                   class="mt-4 inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary/90">
                    {{ __('Pick another time') }}
                </a>
            </div>
        @elseif ($booking->status === BookingStatus::Cancelled)
            <div class="{{ $cardBase }} border-slate-200/80 bg-slate-50 dark:border-slate-800 dark:bg-slate-900/60">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ __('Lesson cancelled') }}</h3>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                    {{ __('Cancelled by the :who.', ['who' => $booking->cancelled_by ?? __('platform')]) }}
                    @if ($booking->cancellation_reason)
                        <span class="italic">“{{ $booking->cancellation_reason }}”</span>
                    @endif
                </p>
                <a href="{{ route('student.teachers.index') }}"
                   class="mt-4 inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-100 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    {{ __('Find another teacher') }}
                </a>
            </div>
        @endif

        <!-- Lesson details -->
        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Lesson') }}</p>
                    <p class="mt-1 text-lg font-bold text-slate-800 dark:text-slate-100">{{ $startLabel }}, {{ $startTime }}–{{ $endLabel }}</p>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $booking->durationMinutes() }} {{ __('minutes') }} · {{ $timezone }}</p>
                </div>
                <div class="text-right">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Teacher') }}</p>
                    <a href="{{ route('student.teachers.show', $booking->teacher_profile_id) }}" class="mt-1 block text-sm font-bold text-primary hover:underline">{{ $teacherUser->name }}</a>
                    <p class="text-xs text-slate-400">{{ $booking->subject?->name }}@if ($booking->lesson) · {{ $booking->lesson->name }}@endif</p>
                </div>
            </div>

            <dl class="mt-5 grid gap-4 border-t border-slate-200 pt-5 text-sm sm:grid-cols-3 dark:border-slate-800">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Learner') }}</dt>
                    <dd class="mt-1 font-semibold text-slate-700 dark:text-slate-200">{{ $booking->learner_name ?? auth()->user()->name }}</dd>
                    @if ($booking->learnerGrade)
                        <dd class="text-xs text-slate-500 dark:text-slate-400">{{ $booking->learnerGrade->label }}</dd>
                    @endif
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Booked on') }}</dt>
                    <dd class="mt-1 font-semibold text-slate-700 dark:text-slate-200">{{ $booking->created_at->format('d M Y, H:i') }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Reference') }}</dt>
                    <dd class="mt-1 font-semibold text-slate-700 dark:text-slate-200">#{{ str_pad((string) $booking->id, 6, '0', STR_PAD_LEFT) }}</dd>
                </div>
            </dl>

            @if ($booking->tutoringRequest)
                <p class="mt-5 border-t border-slate-200 pt-4 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
                    {{ __('This lesson answers') }}
                    <a href="{{ route('student.requests.show', $booking->tutoring_request_id) }}" class="font-semibold text-primary hover:underline">{{ __('your tutoring request') }}</a>.
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

        <!-- Money + actions -->
        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <div class="flex items-center justify-between gap-3">
                <span class="text-sm font-semibold text-slate-600 dark:text-slate-300">
                    {{ $booking->status === BookingStatus::Completed ? __('Paid') : __('Lesson total') }}
                </span>
                <span class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100">{{ $money($booking->totalMinor()) }}</span>
            </div>

            @if ($booking->booking_fee_minor > 0)
                <dl class="mt-3 space-y-1 border-t border-slate-200 pt-3 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
                    <div class="flex items-center justify-between gap-3">
                        <dt>{{ __('Lesson') }}</dt>
                        <dd>{{ $money($booking->price_minor) }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <dt>
                            {{ __('Platform booking fee') }}
                            @if ($booking->bookingFeePromotion)
                                <span class="text-emerald-600 dark:text-emerald-400">· {{ $booking->bookingFeePromotion->name }}</span>
                            @endif
                        </dt>
                        <dd class="{{ $booking->hasBookingFeeDiscount() ? 'text-emerald-600 dark:text-emerald-400' : '' }}">
                            @if ($booking->hasBookingFeeDiscount())
                                {{ $money($booking->netBookingFeeMinor()) }}
                                <span class="ml-1 text-slate-400 line-through">{{ $money($booking->booking_fee_minor) }}</span>
                            @else
                                {{ $money($booking->booking_fee_minor) }}
                            @endif
                        </dd>
                    </div>
                </dl>
            @endif

            @if ($payment)
                <dl class="mt-4 space-y-2 border-t border-slate-200 pt-4 text-sm dark:border-slate-800">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500 dark:text-slate-400">{{ __('Payment') }}</dt>
                        <dd class="flex items-center gap-2">
                            <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $payment->status->badgeClasses() }}">{{ $payment->status->label() }}</span>
                            <span class="text-xs text-slate-400">{{ $payment->gateway_payment_id }}</span>
                        </dd>
                    </div>
                    @if ($payment->refundedMinor() > 0)
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-slate-500 dark:text-slate-400">{{ __('Refunded') }}</dt>
                            <dd class="font-semibold text-emerald-600 dark:text-emerald-400">−{{ $money($payment->refundedMinor()) }}</dd>
                        </div>
                    @endif
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-slate-500 dark:text-slate-400">{{ __('Paid on') }}</dt>
                        <dd class="text-slate-600 dark:text-slate-300">{{ $payment->captured_at?->copy()->setTimezone($timezone)->format('d M Y, H:i') ?? '—' }}</dd>
                    </div>
                </dl>

                <a href="{{ route('receipts.show', $booking) }}"
                   class="mt-4 inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    {{ __('View receipt') }}
                </a>
            @endif

            <div class="mt-5 flex flex-wrap gap-3 border-t border-slate-200 pt-5 dark:border-slate-800">
                @if ($booking->status->isLive())
                    <a href="{{ route('classroom.show', $booking) }}"
                       class="inline-flex items-center gap-2 rounded-xl {{ $booking->canJoinNow() ? 'bg-primary px-4 py-2 text-sm font-bold text-white shadow-sm transition-colors hover:bg-primary/90' : 'border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800' }}">
                        {{ $booking->canJoinNow() ? __('Join lesson') : __('Live classroom') }}
                    </a>
                @endif

                @if ($canCancel)
                    <button type="button" x-data="" @click="$dispatch('open-modal', 'cancel-booking')"
                            class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                        {{ __('Cancel lesson') }}
                    </button>
                @endif

                @if ($booking->status === BookingStatus::Confirmed)
                    <form method="POST" action="{{ route('student.bookings.reschedule', $booking) }}">
                        @csrf
                        <button type="submit"
                                class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                            {{ __('Reschedule') }}
                        </button>
                    </form>
                @endif

                @if ($booking->conversation)
                    <a href="{{ route('messages.show', $booking->conversation) }}"
                       class="inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                        {{ __('Chat with teacher') }}
                    </a>
                @endif
            </div>

            @if (! $canCancel && $booking->status === BookingStatus::Confirmed && $booking->isInsideStudentCancelWindow(platform_settings()->int('student_cancel_window_hours')))
                <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                    {{ __('This lesson starts in less than :hours hours, so self-service cancellation is closed — contact support and we will sort it out.', ['hours' => platform_settings()->int('student_cancel_window_hours')]) }}
                </p>
            @endif
        </div>

        <!-- Review -->
        @if ($booking->status === BookingStatus::Completed)
            @php
                $review = $booking->relationLoaded('review') ? $booking->review : $booking->review()->first();
                $windowDays = config('studylikepro.reviews.edit_window_days');
            @endphp

            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('Your review') }}</h3>
                    @if ($review?->isEditable())
                        <a href="{{ route('student.reviews.show', $booking) }}" class="text-xs font-semibold text-primary hover:underline">{{ __('Edit review') }}</a>
                    @endif
                </div>

                @if ($review === null)
                    <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">
                        {{ __('How did it go? A review takes a minute and helps other students pick the right teacher.') }}
                    </p>
                    <a href="{{ route('student.reviews.show', $booking) }}"
                       class="mt-4 inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary/90">
                        {{ __('Leave a review') }}
                    </a>
                @else
                    <div class="mt-3 rounded-xl border border-slate-200 p-4 dark:border-slate-800">
                        <p class="text-lg text-amber-500">{{ str_repeat('★', $review->rating) }}<span class="text-slate-300 dark:text-slate-600">{{ str_repeat('★', 5 - $review->rating) }}</span>
                            <span class="ml-2 align-middle text-xs font-semibold text-slate-400">{{ $review->rating }}/5</span>
                        </p>
                        @if ($review->comment)
                            <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $review->comment }}</p>
                        @else
                            <p class="mt-2 text-sm italic text-slate-400">{{ __('No comment left.') }}</p>
                        @endif
                        <p class="mt-2 text-xs text-slate-400">
                            {{ __('Published :when', ['when' => $review->created_at->diffForHumans()]) }}
                            @if ($review->wasEdited()) · {{ __('edited :when', ['when' => $review->edited_at->diffForHumans()]) }} @endif
                            @unless ($review->isEditable()) · {{ __('edit window closed') }} @endunless
                        </p>
                    </div>

                    @if ($review->isEditable())
                        <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                            {{ __('You can change this review until :date.', ['date' => $review->editableUntil()->format('d M Y')]) }}
                        </p>
                    @endif
                @endif
            </div>
        @endif
    </div>

    @if ($canCancel)
        <x-modal name="cancel-booking" focusable>
            <form method="POST" action="{{ route('student.bookings.cancel', $booking) }}" class="p-6">
                @csrf
                <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ __('Cancel this lesson?') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('The slot is released immediately. A paid lesson is refunded in full when you cancel outside the :hours-hour window; a teacher cancellation is always refunded.', ['hours' => platform_settings()->int('student_cancel_window_hours')]) }}
                </p>
                <div class="mt-4">
                    <x-input-label for="cancel-reason" :value="__('Reason (optional)')" />
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
    @endif
</x-app-layout>