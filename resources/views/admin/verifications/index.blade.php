@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $tabs = [
        ['key' => 'pending', 'label' => 'Pending', 'count' => $counts['pending']],
        ['key' => 'approved', 'label' => 'Approved', 'count' => $counts['approved']],
        ['key' => 'rejected', 'label' => 'Rejected', 'count' => $counts['rejected']],
        ['key' => 'all', 'label' => 'All', 'count' => array_sum($counts)],
    ];
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Teacher verification') }}</h2>
    </x-slot>

    <div class="space-y-6">
        @if (session('status') === 'teacher-approved')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                Teacher approved — they are now verified and notified by email.
            </div>
        @elseif (session('status') === 'teacher-rejected')
            <div class="rounded-2xl border border-rose-200/80 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
                Application rejected — the teacher has been notified with your notes.
            </div>
        @elseif (session('status') === 'verification-not-pending')
            <div class="rounded-2xl border border-amber-200/80 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300">
                This application is no longer pending — refresh to see its current status.
            </div>
        @endif

        <!-- Status tabs -->
        <div class="flex flex-wrap gap-2">
            @foreach ($tabs as $tab)
                <a href="{{ route('admin.verifications.index', ['status' => $tab['key']]) }}"
                   class="inline-flex items-center gap-2 rounded-xl border px-4 py-2 text-sm font-semibold transition-colors {{ $status === $tab['key'] ? 'border-primary/40 bg-primary/10 text-primary' : 'border-slate-200/80 bg-white text-slate-600 hover:border-primary/40 hover:text-primary dark:border-slate-800/80 dark:bg-slate-900 dark:text-slate-300' }}">
                    {{ $tab['label'] }}
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-400">{{ $tab['count'] }}</span>
                </a>
            @endforeach
        </div>

        <!-- Queue -->
        <div class="{{ $cardBase }} p-0" :class="{{ $cardTheme }}">
            @if ($profiles->isEmpty())
                <div class="p-10 text-center">
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ $status === 'pending' ? 'No applications waiting for review. Great job!' : 'Nothing here yet.' }}
                    </p>
                </div>
            @else
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($profiles as $profile)
                        <li class="flex flex-wrap items-center justify-between gap-4 px-6 py-4">
                            <div class="flex min-w-0 items-center gap-3">
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 font-bold text-sm text-primary">
                                    {{ substr($profile->user->name, 0, 2) }}
                                </div>
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ $profile->user->name }}</p>
                                    <p class="truncate text-xs text-slate-400">{{ $profile->user->email }} · {{ $profile->headline }}</p>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-4 text-xs text-slate-400">
                                <span class="inline-flex items-center gap-1">
                                    <svg class="h-3.5 w-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                                    </svg>
                                    {{ $profile->documents_count }} {{ Str::plural('document', $profile->documents_count) }}
                                </span>
                                <span>Submitted {{ $profile->submitted_at?->diffForHumans() ?? 'not yet' }}</span>
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $profile->verification_status->badgeClasses() }}">
                                    {{ $profile->verification_status->label() }}
                                </span>
                                <a href="{{ route('admin.verifications.show', $profile) }}"
                                   class="rounded-xl bg-primary/10 px-3 py-1.5 text-xs font-semibold text-primary transition-colors hover:bg-primary/20">
                                    Review
                                </a>
                            </div>
                        </li>
                    @endforeach
                </ul>

                <div class="border-t border-slate-100 px-6 py-4 dark:border-slate-800">
                    {{ $profiles->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
