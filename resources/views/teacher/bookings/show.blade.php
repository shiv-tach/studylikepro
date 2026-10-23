@php
    // /teacher/schedule/{booking} is rendered by resources/js/teacher-booking-show.
    // Blade owns the permissions, every formatted time and price, and the route
    // map (see App\Support\TeacherBookingShowConfig); the <noscript> block below
    // repeats the same state, actions and totals without JavaScript.
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <a href="{{ route('teacher.schedule.index') }}" class="text-xs font-semibold text-slate-400 transition-colors hover:text-primary">&larr; {{ __('My lessons') }}</a>
                <h2 class="mt-1 font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ $booking->learner_name ?: $booking->student->name }}</h2>
            </div>
            <x-booking-status-badge :status="$booking->status" class="px-3 py-1 text-xs" />
        </div>
    </x-slot>

    <script type="application/json" id="teacher-booking-show-config">@json($config, $jsonFlags)</script>

    <div id="teacher-booking-show-app" class="w-full"></div>

    {{-- Fallback for a browser without JavaScript: the same answer, actions and
         totals as plain text, links and POST forms. --}}
    <noscript>
        <div class="mx-auto max-w-3xl space-y-6">
            @if ($config['flash'])
                <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4 text-sm text-slate-600 dark:border-slate-800 dark:bg-slate-900/60 dark:text-slate-300">
                    {{ $config['flash']['message'] }}
                </div>
            @endif

            @if ($config['error'])
                <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
                    {{ $config['error'] }}
                </div>
            @endif

            <div class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $config['next']['eyebrow'] }}</p>
                <h3 class="mt-1 text-lg font-bold text-slate-800 dark:text-slate-100">
                    {{ $config['lesson']['dateLabel'] }}, {{ $config['lesson']['timeRange'] }}
                </h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ $config['lesson']['durationLabel'] }} · {{ $config['lesson']['title'] }} · {{ $config['lesson']['reference'] }}
                </p>
                <p class="mt-3 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $config['next']['body'] }}</p>

                <div class="mt-4 flex flex-wrap items-center gap-3">
                    @if ($config['actions']['canJoin'] && $config['join']['joinUrl'])
                        <a href="{{ $config['join']['joinUrl'] }}" class="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white">{{ $config['join']['enterLabel'] }}</a>
                    @endif

                    @if ($config['actions']['canStart'])
                        <form method="POST" action="{{ $config['actions']['start'] }}">
                            @csrf
                            <button type="submit" class="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white">{{ $config['copy']['actions']['start'] }}</button>
                        </form>
                    @endif

                    @if ($config['actions']['canComplete'])
                        <form method="POST" action="{{ $config['actions']['complete'] }}">
                            @csrf
                            <button type="submit" class="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white">{{ $config['copy']['actions']['complete'] }}</button>
                        </form>
                    @endif

                    @if ($config['actions']['canNoShow'])
                        <form method="POST" action="{{ $config['actions']['noShow'] }}">
                            @csrf
                            <button type="submit" class="rounded-xl border border-rose-200 px-4 py-2 text-sm font-semibold text-rose-600 dark:border-rose-900/50 dark:text-rose-300">{{ $config['copy']['actions']['noShow'] }}</button>
                        </form>
                    @endif

                    @if ($config['join']['roomUrl'])
                        <a href="{{ $config['join']['roomUrl'] }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 dark:border-slate-700 dark:text-slate-300">{{ $config['join']['enterLabel'] }}</a>
                    @endif

                    @if ($config['join']['messageUrl'])
                        <a href="{{ $config['join']['messageUrl'] }}" class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 dark:border-slate-700 dark:text-slate-300">{{ $config['join']['messageLabel'] }}</a>
                    @endif
                </div>
            </div>

            <div class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ $config['copy']['details']['title'] }}</h3>
                <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2">
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $config['copy']['details']['learner'] }}</dt>
                        <dd class="mt-0.5 font-semibold text-slate-700 dark:text-slate-200">{{ $config['lesson']['learner'] }}</dd>
                        @if ($config['lesson']['learnerGrade'])
                            <dd class="text-xs text-slate-500 dark:text-slate-400">{{ $config['lesson']['learnerGrade'] }}</dd>
                        @endif
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $config['copy']['details']['account'] }}</dt>
                        <dd class="mt-0.5 font-semibold text-slate-700 dark:text-slate-200">{{ $config['lesson']['account'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $config['copy']['details']['subject'] }}</dt>
                        <dd class="mt-0.5 font-semibold text-slate-700 dark:text-slate-200">{{ $config['lesson']['subject'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $config['copy']['details']['lesson'] }}</dt>
                        <dd class="mt-0.5 font-semibold text-slate-700 dark:text-slate-200">{{ $config['lesson']['lesson'] ?? '—' }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $config['copy']['details']['bookedOn'] }}</dt>
                        <dd class="mt-0.5 font-semibold text-slate-700 dark:text-slate-200">{{ $config['lesson']['bookedOn'] }}</dd>
                    </div>
                    <div>
                        <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $config['copy']['details']['timezone'] }}</dt>
                        <dd class="mt-0.5 font-semibold text-slate-700 dark:text-slate-200">{{ $timezone }}</dd>
                    </div>
                </dl>
            </div>

            <div class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ $config['copy']['money']['title'] }}</h3>
                <div class="mt-3 space-y-2 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-slate-500 dark:text-slate-400">{{ $config['money']['priceLabel'] }}</span>
                        <span class="font-semibold text-slate-800 dark:text-slate-100">{{ $config['money']['price'] }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-slate-500 dark:text-slate-400">{{ $config['money']['feeLabel'] }}</span>
                        <span class="font-semibold text-slate-500 dark:text-slate-400">−{{ $config['money']['fee'] }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-3 border-t border-slate-200 pt-2 dark:border-slate-800">
                        <span class="font-semibold text-slate-600 dark:text-slate-300">{{ $config['money']['payoutLabel'] }}</span>
                        <span class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ $config['money']['payout'] }}</span>
                    </div>
                </div>
                @if ($config['money']['note'])
                    <p class="mt-3 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ $config['money']['note'] }}</p>
                @endif
            </div>

            <div class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ $config['copy']['timeline']['title'] }}</h3>
                <div class="mt-4">
                    <x-booking-timeline :booking="$booking" :timezone="$timezone" />
                </div>
            </div>

            @if ($config['cancel'])
                <div class="rounded-2xl border border-slate-200/80 bg-white p-6 dark:border-slate-800/80 dark:bg-slate-900">
                    <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ $config['cancel']['title'] }}</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ $config['cancel']['body'] }}</p>

                    <form method="POST" action="{{ $config['cancel']['url'] }}" class="mt-3">
                        @csrf
                        <label for="cancel-reason" class="block text-sm font-medium text-slate-700 dark:text-slate-300">{{ $config['cancel']['reasonLabel'] }}</label>
                        <textarea id="cancel-reason" name="reason" rows="3"
                                  class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"></textarea>
                        @if ($config['cancel']['error'])
                            <p class="mt-2 text-sm text-rose-600 dark:text-rose-400">{{ $config['cancel']['error'] }}</p>
                        @endif
                        <button type="submit" class="mt-3 rounded-xl bg-rose-600 px-4 py-2 text-sm font-bold text-white">{{ $config['cancel']['submitLabel'] }}</button>
                    </form>
                </div>
            @endif
        </div>
    </noscript>

    @vite('resources/js/teacher-booking-show/main.js')
</x-app-layout>
