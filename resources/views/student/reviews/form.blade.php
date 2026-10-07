@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $teacher = $booking->teacherProfile->user;
    $lessonTitle = $booking->lesson?->name ?? $booking->subject?->name ?? __('Tutoring lesson');
    $timezone = auth()->user()->studentProfile?->timezone ?? config('studylikepro.default_display_timezone');
    $isEdit = $review !== null;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <a href="{{ route('student.bookings.show', $booking) }}" class="text-xs font-semibold text-slate-400 transition-colors hover:text-primary">&larr; {{ __('Back to the lesson') }}</a>
            <h2 class="mt-1 font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">
                {{ $isEdit ? __('Edit your review') : __('Review this lesson') }}
            </h2>
        </div>
    </x-slot>

    <div class="mx-auto max-w-2xl space-y-6">
        @if ($errors->any())
            <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
                {{ $errors->first() }}
            </div>
        @endif

        @if (! $delivered)
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ __('This lesson has not been delivered yet') }}</h3>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                    {{ __('Reviews open as soon as a lesson is marked as delivered — after that you have :days days to change your mind.', ['days' => $editWindowDays]) }}
                </p>
            </div>
        @elseif ($isEdit && ! $editable)
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ __('The edit window has closed') }}</h3>
                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">
                    {{ __('Reviews can be edited for :days days after they are written. Your review is published as-is — contact support if something is wrong with it.', ['days' => $editWindowDays]) }}
                </p>

                <div class="mt-5 rounded-xl border border-slate-200 p-4 dark:border-slate-800">
                    <p class="text-lg text-amber-500">{{ str_repeat('★', $review->rating) }}<span class="text-slate-300 dark:text-slate-600">{{ str_repeat('★', 5 - $review->rating) }}</span></p>
                    @if ($review->comment)
                        <p class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ $review->comment }}</p>
                    @endif
                    <p class="mt-2 text-xs text-slate-400">
                        {{ __('Written :when', ['when' => $review->created_at->diffForHumans()]) }}
                        @if ($review->wasEdited()) · {{ __('edited :when', ['when' => $review->edited_at->diffForHumans()]) }} @endif
                    </p>
                </div>

                <a href="{{ route('student.bookings.show', $booking) }}"
                   class="mt-5 inline-flex items-center gap-2 rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-600 transition-colors hover:bg-slate-50 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800">
                    {{ __('Back to the lesson') }}
                </a>
            </div>
        @else
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Lesson') }}</p>
                <p class="mt-1 text-lg font-bold text-slate-800 dark:text-slate-100">{{ $lessonTitle }}</p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('with :name', ['name' => $teacher->name]) }} · {{ $booking->starts_at->copy()->setTimezone($timezone)->format('D d M Y, H:i') }}
                </p>
            </div>

            <form method="POST" action="{{ $isEdit ? route('student.reviews.update', $booking) : route('student.reviews.store', $booking) }}"
                  class="{{ $cardBase }}" :class="{{ $cardTheme }}"
                  x-data="{ rating: {{ (int) old('rating', $review?->rating ?? 5) }} }">
                @csrf
                @if ($isEdit)
                    @method('PUT')
                @endif

                <h3 class="text-base font-bold text-slate-800 dark:text-slate-100">{{ $isEdit ? __('Update your review') : __('How was the lesson?') }}</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    {{ __('Your review appears on the teacher profile and helps other students choose. Be honest and specific — no personal contact details.', ['name' => $teacher->name]) }}
                </p>

                <div class="mt-5">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Rating') }}</span>
                    <div class="mt-2 flex items-center gap-1">
                        <template x-for="star in 5" :key="star">
                            <button type="button" @click="rating = star"
                                    class="rounded-lg p-1 text-3xl leading-none transition-transform hover:scale-110 focus:outline-none"
                                    :class="star <= rating ? 'text-amber-400' : 'text-slate-300 dark:text-slate-600'"
                                    :aria-label="star + ' stars'">
                                ★
                            </button>
                        </template>
                        <input type="hidden" name="rating" :value="rating">
                        <span class="ml-3 text-sm font-semibold text-slate-500 dark:text-slate-400" x-text="rating + '/5'"></span>
                    </div>
                    <x-input-error :messages="$errors->get('rating')" class="mt-2" />
                </div>

                <div class="mt-5">
                    <x-input-label for="comment" :value="__('What went well? What could be better? (optional)')" />
                    <textarea id="comment" name="comment" rows="5" maxlength="1000"
                              class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                              placeholder="{{ __('E.g. explained quadratics in a way I finally understood — bring questions next time.') }}">{{ old('comment', $review?->comment) }}</textarea>
                    <x-input-error :messages="$errors->get('comment')" class="mt-2" />
                </div>

                <div class="mt-6 flex flex-wrap items-center gap-3">
                    <x-primary-button>{{ $isEdit ? __('Save changes') : __('Publish review') }}</x-primary-button>
                    <a href="{{ route('student.bookings.show', $booking) }}" class="text-sm font-semibold text-slate-500 hover:text-primary">{{ __('Not now') }}</a>
                </div>

                <p class="mt-4 text-xs text-slate-400">
                    {{ __('You can edit this rating for :days days after publishing.', ['days' => $editWindowDays]) }}
                </p>
            </form>
        @endif
    </div>
</x-app-layout>
