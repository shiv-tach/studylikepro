@php
    use App\Support\TeacherReviewsConfig;

    // /teacher/reviews is rendered by resources/js/teacher-reviews. Blade owns the
    // summary numbers, the star distribution, the formatted review rows, the
    // report reasons and the routes, then hands them over as a JSON island. The
    // <noscript> block below repeats the same content without JavaScript, and the
    // paginator stays Blade-rendered because its links are plain URLs.
    $config = TeacherReviewsConfig::build(
        $teacher,
        $reviews,
        $breakdown,
        $reported,
        $userTheme['preset'] ?? 'classic',
    );

    // JSON_UNESCAPED_SLASHES keeps the URLs readable; the HEX flags keep the
    // island safe inside a <script> tag.
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;

    // The no-JS twin stays preset-independent so it needs no theme JS.
    $cardClass = 'rounded-2xl border p-6 border-slate-200/80 bg-white dark:border-slate-800/80 dark:bg-slate-900';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Reviews received') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                {{ __('What students said after their lessons — and how to report something that looks wrong.') }}
            </p>
        </div>
    </x-slot>

    <div class="w-full">
        <script type="application/json" id="teacher-reviews-config">@json($config, $jsonFlags)</script>

        <div id="teacher-reviews-app" class="space-y-6"></div>

        {{-- Fallback for a browser without JavaScript: the same summary, the same
             distribution and the same reviews, with each report form inline. --}}
        <noscript>
            <div class="space-y-6">
                @if (session('status') === 'review-reported')
                    <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                        {{ __('Thanks — support has this review and will look at it before long.') }}
                    </div>
                @endif

                <div class="grid gap-4 sm:grid-cols-3">
                    <div class="{{ $cardClass }}">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $config['copy']['stats']['rating'] }}</p>
                        <p class="mt-2 text-3xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100">
                            {{ $config['stats']['rating'] }}
                            <span class="text-base font-medium text-slate-400">/5</span>
                        </p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $config['stats']['reviewsLabel'] }}</p>
                    </div>

                    <div class="{{ $cardClass }}">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $config['copy']['stats']['lessons'] }}</p>
                        <p class="mt-2 text-3xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100">{{ $config['stats']['lessons'] }}</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $config['copy']['stats']['lessonsCaption'] }}</p>
                    </div>

                    <div class="{{ $cardClass }}">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $config['copy']['stats']['reported'] }}</p>
                        <p class="mt-2 text-3xl font-extrabold tracking-tight {{ $config['stats']['reported'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-900 dark:text-slate-100' }}">{{ $config['stats']['reported'] }}</p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $config['copy']['stats']['reportedCaption'] }}</p>
                    </div>
                </div>

                <div class="{{ $cardClass }}">
                    <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ $config['copy']['breakdown']['title'] }}</h3>

                    @if ($config['breakdown']['total'] === 0)
                        <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">{{ $config['copy']['breakdown']['empty'] }}</p>
                    @else
                        <div class="mt-4 space-y-2">
                            @foreach ($config['breakdown']['rows'] as $row)
                                <div class="flex items-center gap-3 text-sm">
                                    <span class="w-10 shrink-0 font-semibold text-slate-500 dark:text-slate-400">{{ $row['label'] }}</span>
                                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                        <div class="h-full rounded-full bg-amber-400" style="width: {{ $row['percent'] }}%"></div>
                                    </div>
                                    <span class="w-8 shrink-0 text-right text-xs text-slate-400">{{ $row['count'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                @forelse ($reviews as $review)
                    <div class="{{ $cardClass }}">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-slate-800 dark:text-slate-100">
                                    {{ $review->reviewerName() }}
                                    <span class="ml-1 text-amber-500">{{ str_repeat('★', $review->rating) }}<span class="text-slate-300 dark:text-slate-600">{{ str_repeat('★', 5 - $review->rating) }}</span></span>
                                </p>
                                <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                                    {{ $review->booking?->lesson?->name ?? $review->booking?->subject?->name ?? __('Tutoring lesson') }}
                                    · {{ display_datetime($review->booking?->starts_at, 'd M Y') }}
                                    · {{ __('reviewed :when', ['when' => $review->created_at->diffForHumans()]) }}
                                    @if ($review->wasEdited()) · {{ __('edited') }} @endif
                                </p>
                            </div>

                            @if ($review->flagged_at)
                                <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                                    {{ $config['copy']['card']['reportedChip'] }}
                                </span>
                            @endif
                        </div>

                        @if ($review->comment)
                            <p class="mt-4 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $review->comment }}</p>
                        @else
                            <p class="mt-4 text-sm italic text-slate-400">{{ $config['copy']['card']['noComment'] }}</p>
                        @endif

                        @unless ($review->flagged_at)
                            <details class="mt-4">
                                <summary class="cursor-pointer text-xs font-semibold text-primary">{{ $config['copy']['card']['reportCta'] }}</summary>

                                <form method="POST" action="{{ route('teacher.reviews.flag', $review) }}" class="mt-3 space-y-4">
                                    @csrf
                                    <input type="hidden" name="editing_review" value="{{ $review->id }}">

                                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ $config['copy']['dialog']['intro'] }}</p>

                                    <div>
                                        <x-input-label for="flag-reason-{{ $review->id }}" :value="$config['copy']['dialog']['reasonLabel']" />
                                        <select id="flag-reason-{{ $review->id }}" name="reason" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                            @foreach ($config['flag']['reasons'] as $reason)
                                                <option value="{{ $reason['value'] }}">{{ $reason['label'] }}</option>
                                            @endforeach
                                        </select>
                                        <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                                    </div>

                                    <div>
                                        <x-input-label for="flag-notes-{{ $review->id }}" :value="$config['copy']['dialog']['notesLabel']" />
                                        <textarea id="flag-notes-{{ $review->id }}" name="notes" rows="3" maxlength="{{ $config['flag']['maxNotes'] }}"
                                                  class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"></textarea>
                                        <x-input-error :messages="$errors->get('notes')" class="mt-2" />
                                    </div>

                                    <button type="submit" class="rounded-xl bg-rose-600 px-4 py-2 text-sm font-bold text-white shadow-sm transition-colors hover:bg-rose-500">
                                        {{ $config['copy']['dialog']['submit'] }}
                                    </button>
                                </form>
                            </details>
                        @endunless
                    </div>
                @empty
                    <div class="{{ $cardClass }} text-center">
                        <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 text-2xl">⭐</span>
                        <h3 class="mt-4 text-base font-bold text-slate-800 dark:text-slate-100">{{ $config['copy']['empty']['title'] }}</h3>
                        <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                            {{ $config['copy']['empty']['body'] }}
                        </p>
                    </div>
                @endforelse
            </div>
        </noscript>

        {{-- Laravel keeps pagination server-rendered: the links are URLs and
             markup, which is exactly what Blade still owns. --}}
        @if ($reviews->hasPages())
            <div class="mt-6">{{ $reviews->links() }}</div>
        @endif
    </div>

    @vite('resources/js/teacher-reviews/main.js')
</x-app-layout>
