@php
    use App\Models\Review;

    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $total = array_sum($breakdown);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Reviews received') }}</h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('What students said after their lessons — and how to report something that looks wrong.') }}
                </p>
            </div>
            <a href="{{ route('teachers.show', $teacher) }}" target="_blank" rel="noopener noreferrer"
               class="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                {{ __('See my public profile') }} &nearr;
            </a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        @if (session('status') === 'review-reported')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                {{ __('Thanks — support has this review and will look at it before long.') }}
            </div>
        @endif

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Rating') }}</p>
                <p class="mt-2 text-3xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100">
                    {{ $teacher->rating_count > 0 ? number_format((float) $teacher->rating_avg, 1) : '—' }}
                    <span class="text-base font-medium text-slate-400">/5</span>
                </p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">
                    {{ trans_choice(':count review|:count reviews', $teacher->rating_count) }}
                </p>
            </div>

            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Lessons taught') }}</p>
                <p class="mt-2 text-3xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100">{{ $teacher->lessons_completed_count }}</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Delivered on Studylikepro') }}</p>
            </div>

            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Reported') }}</p>
                <p class="mt-2 text-3xl font-extrabold tracking-tight {{ $reported > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-slate-900 dark:text-slate-100' }}">{{ $reported }}</p>
                <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Reviews under review by support') }}</p>
            </div>
        </div>

        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ __('Rating breakdown') }}</h3>

            @if ($total === 0)
                <p class="mt-3 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('No reviews yet — students can review a lesson as soon as you mark it delivered.') }}
                </p>
            @else
                <div class="mt-4 space-y-2">
                    @foreach ($breakdown as $star => $count)
                        <div class="flex items-center gap-3 text-sm">
                            <span class="w-10 shrink-0 font-semibold text-slate-500 dark:text-slate-400">{{ $star }} ★</span>
                            <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <div class="h-full rounded-full bg-amber-400" style="width: {{ $total > 0 ? round($count / $total * 100) : 0 }}%"></div>
                            </div>
                            <span class="w-8 shrink-0 text-right text-xs text-slate-400">{{ $count }}</span>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        @forelse ($reviews as $review)
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm font-bold text-slate-800 dark:text-slate-100">
                            {{ $review->reviewerName() }}
                            <span class="ml-1 text-amber-500">{{ str_repeat('★', $review->rating) }}<span class="text-slate-300 dark:text-slate-600">{{ str_repeat('★', 5 - $review->rating) }}</span></span>
                        </p>
                        <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                            {{ $review->booking?->topic?->name ?? $review->booking?->subject?->name ?? __('Tutoring lesson') }}
                            · {{ $review->booking?->starts_at?->format('d M Y') }}
                            · {{ __('reviewed :when', ['when' => $review->created_at->diffForHumans()]) }}
                            @if ($review->wasEdited()) · {{ __('edited') }} @endif
                        </p>
                    </div>

                    @if ($review->flagged_at)
                        <span class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-400">
                            {{ __('Reported — under review') }}
                        </span>
                    @else
                        <button type="button" x-data="" @click="$dispatch('open-modal', 'flag-review-{{ $review->id }}')"
                                class="rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                            {{ __('Report this review') }}
                        </button>
                    @endif
                </div>

                @if ($review->comment)
                    <p class="mt-4 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $review->comment }}</p>
                @else
                    <p class="mt-4 text-sm italic text-slate-400">{{ __('No comment left.') }}</p>
                @endif
            </div>

            @unless ($review->flagged_at)
                <x-modal name="flag-review-{{ $review->id }}" focusable>
                    <form method="POST" action="{{ route('teacher.reviews.flag', $review) }}" class="p-6">
                        @csrf
                        <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ __('Report this review') }}</h2>
                        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                            {{ __('Support reviews reports and can take a review off your public profile. Ratings that break the rules are removed; honest criticism stays.') }}
                        </p>

                        <div class="mt-4 space-y-4">
                            <div>
                                <x-input-label for="flag-reason-{{ $review->id }}" :value="__('What is wrong with it?')" />
                                <select id="flag-reason-{{ $review->id }}" name="reason" class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                    @foreach (Review::FLAG_REASONS as $value => $label)
                                        <option value="{{ $value }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('reason')" class="mt-2" />
                            </div>

                            <div>
                                <x-input-label for="flag-notes-{{ $review->id }}" :value="__('Anything support should know? (optional)')" />
                                <textarea id="flag-notes-{{ $review->id }}" name="notes" rows="3" maxlength="500"
                                          class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"></textarea>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end gap-3">
                            <x-secondary-button x-on:click="$dispatch('close')">{{ __('Cancel') }}</x-secondary-button>
                            <x-danger-button>{{ __('Send to support') }}</x-danger-button>
                        </div>
                    </form>
                </x-modal>
            @endunless
        @empty
            <div class="{{ $cardBase }} text-center" :class="{{ $cardTheme }}">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 text-2xl">⭐</span>
                <h3 class="mt-4 text-base font-bold text-slate-800 dark:text-slate-100">{{ __('No reviews yet') }}</h3>
                <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                    {{ __('Students can review a lesson as soon as you mark it delivered. Strong lessons and clear communication are what turn into stars.') }}
                </p>
            </div>
        @endforelse

        @if ($reviews->hasPages())
            <div>{{ $reviews->links() }}</div>
        @endif
    </div>
</x-app-layout>
