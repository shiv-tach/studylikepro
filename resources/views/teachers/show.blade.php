@php
    // The public teacher profile is rendered by resources/js/public inside the
    // landing page's shell. Blade keeps ownership of the grade scope, the rates,
    // the permission checks and the route map, then hands them over as a JSON
    // island — so a visitor keeps the public navigation they arrived with.
    //
    // `$preview` is the teacher's own watermarked view of this page: it renders
    // the same content before approval, marked noindex and never bookable.
    $preview = $preview ?? false;
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT
        | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE;

    $description = $teacher->headline
        ?: Str::limit((string) $teacher->bio, 150)
        ?: __('Book a live 1-on-1 lesson with a verified Sri Lankan teacher.');
@endphp

<x-public-page
    :title="$teacher->user->name.($teacher->headline ? ' — '.$teacher->headline : '')"
    :description="$description"
    :og-image="asset('images/hero/hero-students-1200.jpg')"
    :vite="['resources/css/app.css', 'resources/js/public/main.js']"
    :noindex="$preview"
>
    @if ($preview)
        <div class="sticky top-0 z-50 border-b border-amber-300 bg-amber-50 px-4 py-2.5 text-center text-xs font-semibold text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/80 dark:text-amber-200">
            {{ __('Preview only — your profile is not public yet.') }}
            <a href="{{ route('teacher.approval.status') }}" class="underline">{{ __('Back to approval status') }}</a>
        </div>
    @endif

    <script type="application/json" id="public-teacher-config">@json($config, $jsonFlags)</script>

    <div id="public-teacher-app"></div>

    {{-- Fallback for crawlers and visitors without JavaScript: the same teacher,
         the same grades and the same open slots, stacked and reachable. --}}
    <noscript>
        <div class="mx-auto w-full max-w-4xl px-5 py-10">
            <nav class="flex items-center gap-2 text-sm text-ink-500" aria-label="Breadcrumb">
                <a href="{{ route('teachers.index') }}" class="transition hover:text-ink">{{ __('Teachers') }}</a>
                <span aria-hidden="true">/</span>
                <span class="font-medium text-ink-700">{{ $teacher->user->name }}</span>
            </nav>

            <div class="hairline mt-6 rounded-2xl bg-white p-6">
                <h1 class="font-display text-2xl font-semibold">{{ $teacher->user->name }}</h1>
                @if ($teacher->headline)
                    <p class="mt-1 text-ink-700">{{ $teacher->headline }}</p>
                @endif
                <p class="mt-3 text-sm text-ink-500">
                    {{ $config['teacher']['rateChip'] }}
                    @if ($config['teacher']['rating'])
                        · {{ $config['teacher']['rating'] }} {{ $config['copy']['outOfFive'] }}
                        ({{ $config['teacher']['reviewsLabel'] }})
                    @else
                        · {{ $config['copy']['noReviews'] }}
                    @endif
                </p>

                @if ($config['services']['badges'])
                    <div class="mt-3">
                        <p class="text-[10px] font-semibold tracking-widest text-ink-400 uppercase">{{ $config['services']['title'] }}</p>
                        <ul class="mt-2 flex flex-wrap gap-2">
                            @foreach ($config['services']['badges'] as $badge)
                                <li class="rounded-full bg-ink-50 px-3 py-1 text-xs font-semibold text-ink-700">{{ $badge['label'] }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @if ($teacher->bio)
                    <p class="mt-4 text-sm leading-relaxed text-ink-700">{{ $teacher->bio }}</p>
                @endif
            </div>

            <div class="hairline mt-6 rounded-2xl bg-white p-6">
                <h2 class="font-display text-lg font-semibold">{{ $config['copy']['gradesTitle'] }}</h2>

                @forelse ($config['grades']['groups'] as $group)
                    <div class="mt-4">
                        <p class="text-xs font-semibold tracking-widest text-ink-400 uppercase">{{ $group['level'] }}</p>
                        <ul class="mt-2 flex flex-wrap gap-2">
                            @foreach ($group['grades'] as $grade)
                                <li class="rounded-full bg-ink-50 px-3 py-1 text-xs font-semibold text-ink-700">{{ $grade['chip'] }}</li>
                            @endforeach
                        </ul>
                    </div>
                @empty
                    <p class="mt-3 text-sm text-ink-500">{{ $config['copy']['gradesEmpty'] }}</p>
                @endforelse
            </div>

            <div class="hairline mt-6 rounded-2xl bg-white p-6">
                <h2 class="font-display text-lg font-semibold">{{ $config['copy']['availabilityTitle'] }}</h2>
                <p class="mt-1 text-sm text-ink-500">
                    {{ $config['availability']['timezoneLabel'] }} · {{ $config['availability']['status']['label'] }}
                </p>

                @if ($config['availability']['empty'])
                    <p class="mt-4 rounded-xl bg-ink-50 px-4 py-6 text-center text-sm text-ink-500">
                        {{ $config['copy']['noSlots'] }}
                    </p>
                @else
                    @foreach ($config['availability']['days'] as $day)
                        <div class="mt-4">
                            <p class="text-xs font-semibold tracking-widest text-ink-400 uppercase">{{ $day['fullLabel'] }}</p>
                            <div class="mt-2 flex flex-wrap gap-2">
                                @foreach ($day['slots'] as $slot)
                                    <span class="rounded-lg bg-ink-50 px-2.5 py-1 text-xs font-semibold text-ink-700">{{ $slot['time'] }}</span>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>

            <div class="hairline mt-6 rounded-2xl bg-white p-6">
                <h2 class="font-display text-lg font-semibold">{{ $config['copy']['reviewsTitle'] }}</h2>

                @if ($config['reviews']['count'] === 0)
                    <p class="mt-3 text-sm text-ink-500">{{ $config['copy']['noReviews'] }}</p>
                @else
                    <ul class="mt-3 space-y-3">
                        @foreach ($config['reviews']['items'] as $review)
                            <li class="rounded-xl bg-ink-50 p-4">
                                <p class="text-sm font-semibold">{{ $review['reviewer'] }}</p>
                                <p class="text-xs text-ink-500">{{ $review['stars']['filled'] }}{{ $review['stars']['empty'] }}</p>
                                @if ($review['comment'])
                                    <p class="mt-2 text-sm leading-relaxed text-ink-700">{{ $review['comment'] }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            <p class="mt-6 text-sm">
                @guest
                    <a href="{{ route('login') }}" class="font-semibold text-brand-600">{{ $config['booking']['action']['label'] }}</a>
                    ·
                    <a href="{{ route('register', ['role' => 'student']) }}" class="font-semibold text-brand-600">{{ $config['booking']['secondary']['label'] }}</a>
                @else
                    <a href="{{ route('dashboard') }}" class="font-semibold text-brand-600">{{ $config['booking']['action']['label'] }}</a>
                @endguest
                ·
                <a href="{{ route('teachers.index') }}" class="font-semibold text-brand-600">{{ $config['copy']['findTeacher'] }}</a>
            </p>
        </div>
    </noscript>
</x-public-page>