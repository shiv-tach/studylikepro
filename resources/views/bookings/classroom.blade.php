@php
    use App\Enums\BookingStatus;
    use App\Enums\MeetingStatus;

    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";

    $isLive = $booking->status->isLive();
    $windowOpen = $booking->isJoinWindowOpen();
    $windowClosed = $isLive && now()->greaterThan($booking->joinClosesAt());
    $lessonTitle = $booking->lesson?->name ?? $booking->subject?->name ?? __('Lesson');
    $counterpart = $isTeacher
        ? ($booking->learner_name ?: $booking->student->name)
        : $booking->teacherProfile->user->name;
    $startLabel = $booking->starts_at->copy()->setTimezone($timezone)->format('D d M Y');
    $startTime = $booking->starts_at->copy()->setTimezone($timezone)->format('H:i');
    $endTime = $booking->ends_at->copy()->setTimezone($timezone)->format('H:i');
    $openTime = $booking->joinOpensAt()->copy()->setTimezone($timezone)->format('H:i');

    $state = match (true) {
        ! $isLive => 'lesson_over',
        $booking->meeting_status === MeetingStatus::Failed => 'failed',
        $windowClosed => 'window_closed',
        $windowOpen && $joinUrl !== null => 'open',
        $windowOpen => 'preparing',
        default => 'countdown',
    };
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <a href="{{ $isTeacher ? route('teacher.bookings.show', $booking) : route('student.bookings.show', $booking) }}"
                   class="text-xs font-semibold text-slate-400 transition-colors hover:text-primary">
                    &larr; {{ $isTeacher ? __('Lesson details') : __('My lesson') }}
                </a>
                <h2 class="mt-1 font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">
                    {{ __('Live classroom') }} · {{ $lessonTitle }}
                </h2>
            </div>
            <x-booking-status-badge :status="$booking->status" class="px-3 py-1 text-xs" />
        </div>
    </x-slot>

    <div class="mx-auto max-w-3xl space-y-6">
        @if (session('status') === 'classroom-regenerated')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                {{ __('A fresh classroom has been created — the new link is ready below.') }}
            </div>
        @endif

        @if ($errors->has('meeting'))
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
                {{ $errors->first('meeting') }}
            </div>
        @endif

        @if ($state === 'lesson_over')
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ __('This classroom has closed') }}</h3>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                    @if ($booking->status === BookingStatus::Completed)
                        {{ __('The lesson was delivered on :date. You can find the receipt and the lesson record on the lesson page.', ['date' => $booking->completed_at?->copy()->setTimezone($timezone)->format('d M Y, H:i') ?? $startLabel]) }}
                    @elseif ($booking->status === BookingStatus::NoShow)
                        {{ __('The lesson was marked as a no-show, so no classroom was used. Our team can review what happened.') }}
                    @elseif ($booking->status === BookingStatus::Cancelled)
                        {{ __('The lesson was cancelled, so its classroom is no longer available.') }}
                    @elseif ($booking->status === BookingStatus::Expired)
                        {{ __('The payment window closed before this lesson was paid for.') }}
                    @else
                        {{ __('This lesson is not running, so its classroom is closed.') }}
                    @endif
                </p>
                <a href="{{ $isTeacher ? route('teacher.bookings.show', $booking) : route('student.bookings.show', $booking) }}"
                   class="mt-5 inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    {{ __('Back to the lesson') }}
                </a>
            </div>
        @elseif ($state === 'open')
            <div class="{{ $cardBase }} border-emerald-200/80 bg-emerald-50/60 dark:border-emerald-900/40 dark:bg-emerald-950/20">
                <div class="flex items-start gap-4">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-emerald-100 text-2xl dark:bg-emerald-900/40">🎥</span>
                    <div class="min-w-0">
                        <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ __('The classroom is open') }}</h3>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                            {{ $isTeacher ? __('Your student :name is ready to join.', ['name' => $counterpart]) : __('Your teacher :name is waiting for you.', ['name' => $counterpart]) }}
                        </p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                            {{ $startTime }}–{{ $endTime }} · {{ $booking->durationMinutes() }} {{ __('minutes') }} · {{ __('link live until :time', ['time' => $booking->joinClosesAt()->copy()->setTimezone($timezone)->format('H:i')]) }}
                        </p>
                    </div>
                </div>

                <a href="{{ $joinUrl }}" target="_blank" rel="noopener noreferrer"
                   class="mt-5 inline-flex w-full items-center justify-center gap-2 rounded-xl bg-primary px-6 py-3 text-base font-bold text-white shadow-sm transition-colors hover:bg-primary/90 sm:w-auto">
                    {{ __('Join the lesson') }} &nearr;
                </a>
                <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">
                    {{ __('The classroom opens in a new tab. Allow camera and microphone access, and check your speakers before you begin.') }}
                </p>

                @if ($isTeacher)
                    <div class="mt-5 flex flex-wrap gap-3 border-t border-emerald-200/60 pt-5 dark:border-emerald-900/30">
                        @if ($booking->status === BookingStatus::Confirmed)
                            <form method="POST" action="{{ route('teacher.bookings.start', $booking) }}">
                                @csrf
                                <x-primary-button>{{ __('Start lesson') }}</x-primary-button>
                            </form>
                        @endif
                        @if ($booking->status->isLive())
                            <form method="POST" action="{{ route('teacher.bookings.complete', $booking) }}">
                                @csrf
                                <x-secondary-button>{{ __('Mark as delivered') }}</x-secondary-button>
                            </form>
                        @endif
                    </div>
                @endif
            </div>
        @elseif ($state === 'failed')
            <div class="{{ $cardBase }} border-rose-200/80 bg-rose-50/70 dark:border-rose-900/40 dark:bg-rose-950/20">
                <div class="flex items-start gap-4">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-2xl dark:bg-rose-900/40">⚠️</span>
                    <div>
                        <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ __('The classroom could not be opened') }}</h3>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                            {{ $isTeacher
                                ? __('We could not reach the video provider. Try again — it usually fixes itself in a second.')
                                : __('Our team has been notified and is opening a fresh room. Keep this page open; the join button appears as soon as it is ready.') }}
                        </p>
                        @if ($booking->meeting_error)
                            <p class="mt-3 rounded-xl bg-white/70 px-3 py-2 text-xs text-slate-500 dark:bg-slate-900/60 dark:text-slate-400">
                                {{ \Illuminate\Support\Str::limit($booking->meeting_error, 160) }}
                            </p>
                        @endif
                    </div>
                </div>

                @if ($isTeacher || auth()->user()->isAdmin())
                    <form method="POST" action="{{ route('classroom.retry', $booking) }}" class="mt-5">
                        @csrf
                        <x-primary-button>{{ __('Try again') }}</x-primary-button>
                    </form>
                @endif
            </div>
        @elseif ($state === 'preparing')
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}" x-data x-init="setTimeout(() => window.location.reload(), 5000)">
                <div class="flex items-start gap-4">
                    <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-2xl dark:bg-amber-900/40">⏳</span>
                    <div>
                        <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ __('Preparing your classroom') }}</h3>
                        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                            {{ __('We are opening the room with our video provider. This page refreshes by itself — the join button appears here.') }}
                        </p>
                    </div>
                </div>
            </div>
        @elseif ($state === 'window_closed')
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ __('The classroom has closed') }}</h3>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                    {{ __('Joins close :minutes minutes after the lesson was due to end.', ['minutes' => $booking->joinClosesAfterMinutes()]) }}
                </p>

                @if ($isTeacher)
                    <form method="POST" action="{{ route('teacher.bookings.complete', $booking) }}" class="mt-5">
                        @csrf
                        <x-primary-button>{{ __('Mark as delivered') }}</x-primary-button>
                    </form>
                @else
                    <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">
                        {{ __('If the lesson went ahead, your teacher will mark it as delivered and your receipt appears on the lesson page.') }}
                    </p>
                @endif
            </div>
        @else
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ __('Your classroom opens soon') }}</h3>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                    {{ __('Joins open :minutes minutes before the start time, at :time.', [
                        'minutes' => $booking->joinOpensBeforeMinutes(),
                        'time' => $openTime,
                    ]) }}
                </p>

                <div class="mt-5 rounded-xl bg-slate-50 px-4 py-4 text-center dark:bg-slate-800/60"
                     x-data="{
                         seconds: {{ $secondsUntilOpen }},
                         get label() {
                             const h = String(Math.floor(this.seconds / 3600)).padStart(2, '0');
                             const m = String(Math.floor((this.seconds % 3600) / 60)).padStart(2, '0');
                             const s = String(this.seconds % 60).padStart(2, '0');
                             return h === '00' ? m + ':' + s : h + ':' + m + ':' + s;
                         },
                         init() {
                             this.timer = setInterval(() => {
                                 this.seconds--;
                                 if (this.seconds <= 0) {
                                     clearInterval(this.timer);
                                     window.location.reload();
                                 }
                             }, 1000);
                         },
                     }">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Join opens in') }}</p>
                    <p class="mt-1 font-mono text-3xl font-extrabold tabular-nums text-slate-800 dark:text-slate-100" x-text="label"></p>
                    <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ $startLabel }}, {{ $startTime }}–{{ $endTime }} ({{ $timezone }})</p>
                </div>

                @if ($isTeacher && ! $booking->meetingIsReady())
                    <form method="POST" action="{{ route('classroom.retry', $booking) }}" class="mt-5">
                        @csrf
                        <x-secondary-button>{{ __('Open the classroom now') }}</x-secondary-button>
                    </form>
                @endif
            </div>
        @endif

        <!-- Lesson details -->
        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('Lesson details') }}</h3>
            <dl class="mt-4 grid gap-4 text-sm sm:grid-cols-3">
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $isTeacher ? __('Learner') : __('Teacher') }}</dt>
                    <dd class="mt-1 font-semibold text-slate-700 dark:text-slate-200">{{ $counterpart }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('When') }}</dt>
                    <dd class="mt-1 font-semibold text-slate-700 dark:text-slate-200">{{ $startLabel }}</dd>
                    <dd class="text-xs text-slate-500 dark:text-slate-400">{{ $startTime }}–{{ $endTime }} · {{ $timezone }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Reference') }}</dt>
                    <dd class="mt-1 font-semibold text-slate-700 dark:text-slate-200">#{{ str_pad((string) $booking->id, 6, '0', STR_PAD_LEFT) }}</dd>
                    @if ($booking->meeting_status)
                        <dd class="mt-1">
                            <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $booking->meeting_status->badgeClasses() }}">
                                {{ $booking->meeting_status->label() }}
                            </span>
                        </dd>
                    @endif
                </div>
            </dl>
        </div>
    </div>
</x-app-layout>
