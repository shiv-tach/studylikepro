@php
    // The public subject page is rendered by resources/js/public. Blade keeps
    // ownership of the copy, the prices, the permission checks and the route
    // map, then hands them over as a JSON island — the same island the catalog
    // fetches when a subject is opened on click, without a page reload.
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
@endphp

<x-public-page
    :title="$subject->name"
    :description="$subject->description"
    :og-image="asset('images/hero/hero-students-1200.jpg')"
    :vite="['resources/css/app.css', 'resources/js/public/main.js']"
>
    <script type="application/json" id="public-subject-config">@json($config, $jsonFlags)</script>

    <div id="public-subject-app"></div>

    {{-- Fallback for crawlers and visitors without JavaScript: the plain page
         the Vue app renders, with every section stacked and reachable. --}}
    <noscript>
        <div class="mx-auto w-full max-w-6xl px-4 py-10 sm:px-6 lg:px-8">
            <nav class="flex items-center gap-2 text-sm text-slate-400">
                <a href="{{ route('catalog.subjects.index') }}" class="font-medium transition-colors hover:text-primary">{{ __('Subjects') }}</a>
                <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                </svg>
                <span class="font-medium text-slate-600">{{ $subject->name }}</span>
            </nav>

            @include('catalog._subject-content', [
                'subject' => $subject,
                'level' => $level,
                'grade' => $grade,
                'lessons' => $lessons,
                'teachers' => $teachers,
                'teacherTotal' => $teacherTotal,
                'nextSlots' => $nextSlots,
                'offeringGroups' => $offeringGroups,
                'gradesById' => $gradesById,
            ])

            <div class="mt-12 rounded-2xl border border-slate-200/80 bg-white p-6">
                <h2 class="text-base font-bold text-slate-800">{{ __('Want help with :subject?', ['subject' => $subject->name]) }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ __('Find a verified teacher who teaches this subject for your grade and book a live lesson.') }}</p>
                <p class="mt-4">
                    @auth
                        <a href="{{ route('dashboard') }}" class="font-semibold text-primary hover:underline">{{ __('Go to my dashboard') }}</a>
                    @else
                        <a href="{{ route('register', ['role' => 'student']) }}" class="font-semibold text-primary hover:underline">{{ __('Start learning free') }}</a>
                    @endauth
                </p>
            </div>
        </div>
    </noscript>
</x-public-page>
