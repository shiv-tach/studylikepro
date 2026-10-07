@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $timezone = auth()->user()->studentProfile?->timezone ?? config('studylikepro.default_display_timezone');
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('My tutoring requests') }}</h2>
            <a href="{{ route('student.requests.create') }}"
               class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary/90">
                {{ __('Ask a question') }}
            </a>
        </div>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        @if (session('status') === 'request-cancelled')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                Request cancelled. Any teacher proposals for it have been withdrawn.
            </div>
        @endif

        @forelse ($requests as $tutoringRequest)
            <a href="{{ route('student.requests.show', $tutoringRequest) }}"
               class="block transition-colors hover:border-primary/40 {{ $cardBase }}" :class="{{ $cardTheme }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $tutoringRequest->status->badgeClasses() }}">
                                {{ $tutoringRequest->status->label() }}
                            </span>
                            @unless ($tutoringRequest->classification_status->isSettled())
                                <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $tutoringRequest->classification_status->badgeClasses() }}">
                                    {{ $tutoringRequest->classification_status->label() }}
                                </span>
                            @endunless
                            @if ($tutoringRequest->responses_count > 0)
                                <span class="rounded-full bg-primary/10 px-2.5 py-0.5 text-[11px] font-semibold text-primary">
                                    {{ trans_choice(':count teacher proposal|:count teacher proposals', $tutoringRequest->responses_count, ['count' => $tutoringRequest->responses_count]) }}
                                </span>
                            @endif
                        </div>

                        <h3 class="mt-3 truncate text-sm font-bold text-slate-800 dark:text-slate-100">
                            {{ $tutoringRequest->lesson?->name ?? Str::limit($tutoringRequest->description, 60) }}
                        </h3>
                        <p class="mt-1 line-clamp-2 text-sm text-slate-500 dark:text-slate-400">{{ $tutoringRequest->description }}</p>
                    </div>

                    <div class="text-right text-xs text-slate-400">
                        <p>{{ $tutoringRequest->created_at->format('d M Y') }}</p>
                        @if ($tutoringRequest->isOpen() && $tutoringRequest->expires_at)
                            <p class="mt-1">{{ __('Open until :date', ['date' => $tutoringRequest->expires_at->format('d M')]) }}</p>
                        @endif
                    </div>
                </div>

                @php $windows = $tutoringRequest->windowLabels($timezone); @endphp
                @if ($windows !== [])
                    <div class="mt-4 flex flex-wrap gap-2">
                        @foreach ($windows as $label)
                            <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                🕒 {{ $label }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </a>
        @empty
            <div class="{{ $cardBase }} text-center" :class="{{ $cardTheme }}">
                <span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10 text-2xl">💬</span>
                <h3 class="mt-4 text-base font-bold text-slate-800 dark:text-slate-100">No requests yet</h3>
                <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                    Describe what you are stuck on, add a photo of the problem if you have one, and we will match it to the right lesson and teacher.
                </p>
                <a href="{{ route('student.requests.create') }}"
                   class="mt-5 inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary/90">
                    {{ __('Ask your first question') }}
                </a>
            </div>
        @endforelse

        @if ($requests->hasPages())
            <div>{{ $requests->links() }}</div>
        @endif
    </div>
</x-app-layout>
