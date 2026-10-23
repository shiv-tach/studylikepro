@php
    use App\Enums\BookingStatus;

    // /teacher/schedule is rendered by resources/js/teacher-schedule. Blade owns
    // the ranked priority queue, every formatted time and price, the agenda
    // grouping and the route map, then hands them over in one JSON island. The
    // <noscript> block below repeats the three views with plain links and forms.
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);
    $startEarlyMinutes = (int) config('studylikepro.booking.teacher_start_early_minutes');

    // JSON_UNESCAPED_SLASHES keeps the URLs readable; the HEX flags keep the
    // island safe inside a <script> tag.
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('My lessons') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Times shown in :timezone.', ['timezone' => $timezone]) }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="{{ route('teacher.availability.index') }}"
                   class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary/90">
                    {{ __('Edit availability') }}
                </a>
            </div>
        </div>
    </x-slot>

    <div class="w-full">
        <script type="application/json" id="teacher-schedule-config">@json($config, $jsonFlags)</script>

        <div id="teacher-schedule-app" class="space-y-6"></div>

        {{-- Fallback for a browser without JavaScript: the same three views as
             plain lists, with the transitions as ordinary POST forms. --}}
        <noscript>
            <div class="mx-auto max-w-4xl space-y-6">
                @if ($holds->isNotEmpty())
                    <section>
                        <h3 class="mb-3 text-sm font-bold uppercase tracking-wider text-slate-400">{{ $config['copy']['holds']['title'] }}</h3>
                        <div class="space-y-3">
                            @foreach ($holds as $booking)
                                <x-booking-card :booking="$booking" :timezone="$timezone" perspective="teacher"
                                                :href="route('teacher.bookings.show', $booking)"
                                                :class="$cardBase.' '.$cardTheme" />
                            @endforeach
                        </div>
                    </section>
                @endif

                <section>
                    <h3 class="mb-3 text-sm font-bold uppercase tracking-wider text-slate-400">{{ $config['copy']['agenda']['title'] }}</h3>

                    @forelse ($upcoming as $booking)
                        <div class="mb-3 space-y-2">
                            <x-booking-card :booking="$booking" :timezone="$timezone" perspective="teacher"
                                            :href="route('teacher.bookings.show', $booking)"
                                            :class="$cardBase.' '.$cardTheme" />

                            <div class="flex flex-wrap gap-2">
                                @if ($booking->status === BookingStatus::Confirmed
                                        && now()->greaterThanOrEqualTo($booking->starts_at->copy()->subMinutes($startEarlyMinutes)))
                                    <form method="POST" action="{{ route('teacher.bookings.start', $booking) }}">
                                        @csrf
                                        <input type="hidden" name="return" value="schedule">
                                        <button type="submit" class="rounded-xl bg-primary px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-primary/90">
                                            {{ $config['copy']['actions']['start'] }}
                                        </button>
                                    </form>
                                @endif

                                @if ($booking->status->isLive())
                                    <form method="POST" action="{{ route('teacher.bookings.complete', $booking) }}">
                                        @csrf
                                        <input type="hidden" name="return" value="schedule">
                                        <button type="submit" class="rounded-xl bg-primary px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-primary/90">
                                            {{ $config['copy']['actions']['complete'] }}
                                        </button>
                                    </form>
                                @endif

                                <a href="{{ route('teacher.bookings.show', $booking) }}"
                                   class="rounded-xl border border-slate-200/80 px-3 py-1.5 text-xs font-semibold text-slate-600 transition-colors hover:border-primary/40 hover:text-primary dark:border-slate-800/80 dark:text-slate-300">
                                    {{ $config['copy']['actions']['open'] }}
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                            <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ $config['copy']['agenda']['emptyTitle'] }}</h3>
                            <p class="mt-2 text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ $config['copy']['agenda']['emptyBody'] }}</p>
                            <p class="mt-2">
                                <a href="{{ $config['urls']['availability'] }}" class="text-sm font-semibold text-primary hover:underline">{{ $config['copy']['agenda']['emptyCta'] }}</a>
                            </p>
                        </div>
                    @endforelse
                </section>

                @if ($past->isNotEmpty())
                    <section>
                        <h3 class="mb-3 text-sm font-bold uppercase tracking-wider text-slate-400">{{ $config['copy']['history']['title'] }}</h3>
                        <div class="{{ $cardBase }} divide-y divide-slate-200 p-0 dark:divide-slate-800" :class="{{ $cardTheme }}">
                            @foreach ($past as $booking)
                                <div class="flex flex-wrap items-center justify-between gap-3 px-6 py-4">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <x-booking-status-badge :status="$booking->status" />
                                            <span class="text-xs text-slate-400">{{ display_datetime($booking->starts_at->copy()->setTimezone($timezone), 'D d M Y') }}</span>
                                        </div>
                                        <p class="mt-1 truncate text-sm font-semibold text-slate-800 dark:text-slate-100">
                                            {{ $booking->learner_name ?: $booking->student->name }}
                                            · {{ $booking->subject?->name }}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $money($booking->teacher_payout_minor) }}</p>
                                        <a href="{{ route('teacher.bookings.show', $booking) }}" class="text-xs font-semibold text-primary hover:underline">{{ $config['copy']['history']['view'] }}</a>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>
        </noscript>
    </div>

    @vite('resources/js/teacher-schedule/main.js')
</x-app-layout>