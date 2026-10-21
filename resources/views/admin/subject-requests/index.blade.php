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
        <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Subject requests') }}</h2>
    </x-slot>

    <div class="space-y-6">
        @if (session('status') === 'request-approved')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                Request approved — the subject is granted (and created in the catalog when it was a proposal) and the teacher has been notified.
            </div>
        @elseif (session('status') === 'request-rejected')
            <div class="rounded-2xl border border-rose-200/80 bg-rose-50 p-4 text-sm text-rose-800 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
                Request rejected — the teacher has been notified with your notes.
            </div>
        @elseif (session('status') === 'request-not-pending')
            <div class="rounded-2xl border border-amber-200/80 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300">
                This request is no longer pending — refresh to see its current status.
            </div>
        @endif

        <!-- Status tabs -->
        <div class="flex flex-wrap gap-2">
            @foreach ($tabs as $tab)
                <a href="{{ route('admin.subject-requests.index', ['status' => $tab['key']]) }}"
                   class="inline-flex items-center gap-2 rounded-xl border px-4 py-2 text-sm font-semibold transition-colors {{ $status === $tab['key'] ? 'border-primary/40 bg-primary/10 text-primary' : 'border-slate-200/80 bg-white text-slate-600 hover:border-primary/40 hover:text-primary dark:border-slate-800/80 dark:bg-slate-900 dark:text-slate-300' }}">
                    {{ $tab['label'] }}
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-bold text-slate-500 dark:bg-slate-800 dark:text-slate-400">{{ $tab['count'] }}</span>
                </a>
            @endforeach
        </div>

        <!-- Queue -->
        <div class="{{ $cardBase }} p-0" :class="{{ $cardTheme }}">
            @if ($requests->isEmpty())
                <div class="p-10 text-center">
                    <p class="text-sm text-slate-500 dark:text-slate-400">
                        {{ $status === 'pending' ? 'No subject requests waiting for review.' : 'Nothing here yet.' }}
                    </p>
                </div>
            @else
                <ul class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($requests as $subjectRequest)
                        <li class="px-6 py-4">
                            <div class="flex flex-wrap items-start justify-between gap-4">
                                <div class="flex min-w-0 items-center gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-sm font-bold text-primary">
                                        {{ substr($subjectRequest->teacherProfile->user->name, 0, 2) }}
                                    </div>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-semibold text-slate-800 dark:text-slate-100">
                                            {{ $subjectRequest->teacherProfile->user->name }}
                                        </p>
                                        <p class="truncate text-xs text-slate-400">{{ $subjectRequest->teacherProfile->user->email }}</p>
                                    </div>
                                </div>

                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $subjectRequest->status->badgeClasses() }}">
                                    {{ $subjectRequest->status->label() }}
                                </span>
                            </div>

                            <div class="mt-3 rounded-xl border border-slate-200/80 px-4 py-3 dark:border-slate-800">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">
                                        {{ $subjectRequest->subject?->icon ?? '🆕' }} {{ $subjectRequest->subject?->name ?? $subjectRequest->proposed_name }}
                                    </span>
                                    @if ($subjectRequest->isNewSubject())
                                        <span class="rounded-full bg-primary/10 px-2.5 py-0.5 text-[11px] font-semibold text-primary">New subject</span>
                                        <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                                            {{ $subjectRequest->educationLevel?->name ?? 'No level' }}
                                        </span>
                                    @endif
                                </div>
                                @if ($subjectRequest->note)
                                    <p class="mt-1.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ $subjectRequest->note }}</p>
                                @endif
                                @if ($subjectRequest->admin_note)
                                    <p class="mt-1.5 rounded-lg bg-rose-50 px-3 py-2 text-xs text-rose-700 dark:bg-rose-950/30 dark:text-rose-300">
                                        Reviewer notes: {{ $subjectRequest->admin_note }}
                                    </p>
                                @endif
                            </div>

                            @if ($subjectRequest->status->value === 'pending')
                                <div class="mt-3 flex flex-wrap items-center gap-3">
                                    <form method="POST" action="{{ route('admin.subject-requests.approve', $subjectRequest) }}">
                                        @csrf
                                        <x-primary-button>{{ __('Approve') }}</x-primary-button>
                                    </form>

                                    <form method="POST" action="{{ route('admin.subject-requests.reject', $subjectRequest) }}" class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                                        @csrf
                                        <input type="text" name="admin_note" required minlength="3" maxlength="1000"
                                               placeholder="Reason (shown to the teacher)"
                                               class="min-w-48 flex-1 rounded-xl border-slate-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-rose-400 focus:ring-rose-400 dark:border-slate-700 dark:bg-slate-900" />
                                        <button type="submit"
                                                class="inline-flex items-center justify-center rounded-xl border border-rose-200/80 px-4 py-2 text-sm font-bold text-rose-600 transition-colors hover:bg-rose-50 dark:border-rose-900/40 dark:text-rose-400 dark:hover:bg-rose-950/30">
                                            Reject
                                        </button>
                                    </form>
                                </div>
                            @endif
                        </li>
                    @endforeach
                </ul>

                <div class="border-t border-slate-100 px-6 py-4 dark:border-slate-800">
                    {{ $requests->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
