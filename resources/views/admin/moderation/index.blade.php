@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $chip = 'inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Moderation') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Reviews teachers have reported, and applications waiting on a decision.') }}</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6">
        @if (session('status') === 'review-hidden')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                {{ __('Review hidden — it no longer shows on the profile and the rating has been recalculated.') }}
            </div>
        @elseif (session('status') === 'review-report-dismissed')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                {{ __('Report dismissed — the review stays published.') }}
            </div>
        @endif

        <!-- Reported reviews -->
        <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Reported reviews') }}</h3>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Hide a review that breaks the rules, or dismiss the report and keep it published.') }}</p>
                </div>
                <span class="{{ $chip }}">{{ trans_choice('{0} None open|{1} :count open|[2,*] :count open', $reviews->total(), ['count' => $reviews->total()]) }}</span>
            </div>

            <ul class="mt-5 divide-y divide-slate-100 dark:divide-slate-800">
                @forelse ($reviews as $review)
                    <li class="py-4 first:pt-0 last:pb-0">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="min-w-0">
                                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                                    ★ {{ $review->rating }} · {{ $review->student?->name }} → {{ $review->teacherProfile?->user?->name }}
                                </p>
                                <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">{{ $review->comment ?: __('No comment left.') }}</p>
                                <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                    {{ __('Reported :when by the teacher', ['when' => $review->flagged_at?->diffForHumans()]) }}
                                    · {{ __('Reason: :reason', ['reason' => $review->flagReasonLabel()]) }}
                                </p>
                                @if ($review->flag_notes)
                                    <p class="mt-1 rounded-xl bg-slate-50 p-2.5 text-xs text-slate-600 dark:bg-slate-800/40 dark:text-slate-300">{{ $review->flag_notes }}</p>
                                @endif
                                @if ($review->booking)
                                    <a href="{{ route('admin.bookings.show', $review->booking) }}" class="mt-2 inline-block text-xs font-semibold text-primary hover:underline">
                                        {{ __('Lesson #:id', ['id' => str_pad((string) $review->booking->id, 6, '0', STR_PAD_LEFT)]) }}
                                        @if ($review->booking->subject) · {{ $review->booking->subject->name }} @endif
                                    </a>
                                @endif
                            </div>

                            <div class="flex shrink-0 gap-2">
                                <form method="POST" action="{{ route('admin.moderation.reviews.dismiss', $review) }}">
                                    @csrf
                                    <button type="submit"
                                            class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-sm transition-colors hover:border-primary hover:text-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                        {{ __('Keep review') }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('admin.moderation.reviews.hide', $review) }}">
                                    @csrf
                                    <button type="submit"
                                            class="inline-flex items-center rounded-xl bg-rose-600 px-3 py-2 text-xs font-semibold text-white shadow-sm transition-colors hover:bg-rose-700">
                                        {{ __('Hide review') }}
                                    </button>
                                </form>
                            </div>
                        </div>
                    </li>
                @empty
                    <li class="py-6 text-sm text-slate-500 dark:text-slate-400">{{ __('Nothing reported right now.') }}</li>
                @endforelse
            </ul>

            @if ($reviews->hasPages())
                <div class="mt-4">{{ $reviews->links() }}</div>
            @endif
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Verification queue -->
            <div class="{{ $cardBase }} lg:col-span-2" :class="{{ $cardTheme }}">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Waiting on verification') }}</h3>
                    <a href="{{ route('admin.verifications.index') }}" class="text-xs font-semibold text-primary hover:underline">{{ __('Full queue →') }}</a>
                </div>

                <ul class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($verificationQueue as $profile)
                        <li class="flex items-center justify-between gap-4 py-3 first:pt-0 last:pb-0">
                            <div>
                                <a href="{{ route('admin.verifications.show', $profile) }}" class="text-sm font-semibold text-slate-800 hover:text-primary dark:text-slate-100">
                                    {{ $profile->user->name }}
                                </a>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $profile->headline }}
                                    · {{ __('submitted :when', ['when' => $profile->submitted_at?->diffForHumans() ?? __('recently')]) }}
                                </p>
                            </div>
                            <a href="{{ route('admin.verifications.show', $profile) }}" class="shrink-0 text-xs font-semibold text-primary hover:underline">{{ __('Review') }}</a>
                        </li>
                    @empty
                        <li class="py-4 text-sm text-slate-500 dark:text-slate-400">{{ __('Nobody waiting — the queue is clear.') }}</li>
                    @endforelse
                </ul>
            </div>

            <!-- Stats -->
            <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Right now') }}</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500 dark:text-slate-400">{{ __('Pending verifications') }}</dt>
                        <dd class="font-semibold text-slate-800 dark:text-slate-100">{{ $pendingVerifications }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500 dark:text-slate-400">{{ __('Reported reviews') }}</dt>
                        <dd class="font-semibold text-slate-800 dark:text-slate-100">{{ $reviews->total() }}</dd>
                    </div>
                    <div class="flex items-center justify-between">
                        <dt class="text-slate-500 dark:text-slate-400">{{ __('Hidden reviews') }}</dt>
                        <dd class="font-semibold text-slate-800 dark:text-slate-100">{{ $hiddenReviews }}</dd>
                    </div>
                </dl>

                <p class="mt-4 text-xs text-slate-500 dark:text-slate-400">
                    {{ __('Hiding a review recalculates the teacher\'s rating straight away.') }}
                </p>
            </div>
        </div>
    </div>
</x-app-layout>
