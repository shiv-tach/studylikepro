@php
    $cardBase = 'rounded-2xl border p-6 transition-all hover:shadow-md hover:border-primary/40 dark:hover:border-primary/30';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $chip = 'inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400';
@endphp
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">
            {{ __('Admin Console') }}
        </h2>
    </x-slot>

    <div class="space-y-6">
        <!-- Welcome banner -->
        <div class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-slate-800 via-slate-900 to-primary p-6 text-white shadow-lg shadow-primary/10 dark:shadow-primary/5">
            <div class="relative z-10 max-w-xl">
                <h3 class="text-xl font-bold md:text-2xl">Welcome back, {{ Auth::user()->name }} 👋</h3>
                <p class="mt-1 text-sm text-white/90">Verification, bookings, payments, disputes, refunds and reports all live here. Anything marked with a number needs a human.</p>
            </div>
            <div class="absolute right-0 top-0 -mr-6 -mt-6 h-36 w-36 rounded-full bg-white/10 blur-xl"></div>
            <div class="absolute right-20 bottom-0 -mb-10 h-28 w-28 rounded-full bg-white/10 blur-lg"></div>
        </div>

        <!-- Needs attention -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @php
                $attention = [
                    ['Open disputes', $openDisputes + 0, 'Investigate and resolve', route('admin.disputes.index'), $openDisputes > 0],
                    ['Flagged reviews', $flaggedReviews, 'Moderate the report', route('admin.moderation.index'), $flaggedReviews > 0],
                    ['Pending verifications', $pendingVerifications, $pendingVerifications > 0 ? 'Review now' : 'Queue is clear', route('admin.verifications.index'), $pendingVerifications > 0],
                    ['Suspended accounts', $suspendedUsers, 'Manage people', route('admin.users.index', ['status' => 'suspended']), false],
                ];
            @endphp

            @foreach ($attention as [$label, $value, $note, $link, $urgent])
                <a href="{{ $link }}" class="{{ $cardBase }} block" :class="{{ $cardTheme }}">
                    <span class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $label }}</span>
                    <h3 class="mt-3 text-2xl font-bold tracking-tight {{ $urgent ? 'text-rose-600 dark:text-rose-400' : 'text-slate-800 dark:text-slate-100' }}">{{ $value }}</h3>
                    <p class="mt-1 inline-flex items-center gap-1 text-xs font-semibold {{ $urgent ? 'text-primary' : 'text-slate-400 dark:text-slate-500' }}">{{ $note }} →</p>
                </a>
            @endforeach
        </div>

        <!-- Money & health, last 30 days -->
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @php
                $moneyTiles = [
                    ['Collected (30 days)', platform_settings()->formatMinor($money['gross']), 'text-emerald-600 dark:text-emerald-400'],
                    ['Platform commission', platform_settings()->formatMinor($money['commission']), 'text-primary'],
                    ['Refunded (30 days)', platform_settings()->formatMinor($money['refunded']), 'text-rose-600 dark:text-rose-400'],
                    ['Payouts due', platform_settings()->formatMinor($money['payouts_due']), 'text-amber-600 dark:text-amber-400'],
                    ['Lessons completed (30 days)', (string) $money['lessons'], 'text-slate-800 dark:text-slate-100'],
                    ['Platform health', $meetingFailures + $stuckPayments > 0 ? $meetingFailures.' classroom · '.$stuckPayments.' payment' : 'All clear', $meetingFailures + $stuckPayments > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400'],
                ];
            @endphp

            @foreach ($moneyTiles as [$label, $value, $tone])
                <div class="{{ $cardBase }} hover:shadow-none" :class="{{ $cardTheme }}">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $label }}</p>
                    <p class="mt-2 text-xl font-extrabold tracking-tight {{ $tone }}">{{ $value }}</p>
                </div>
            @endforeach
        </div>

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Recent disputes -->
            <div class="lg:col-span-2 {{ $cardBase }} hover:shadow-none" :class="{{ $cardTheme }}">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Recent disputes') }}</h3>
                    <a href="{{ route('admin.disputes.index') }}" class="text-xs font-semibold text-primary hover:underline">{{ __('Open the desk →') }}</a>
                </div>

                <ul class="mt-4 divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($recentDisputes as $dispute)
                        <li class="flex items-start justify-between gap-4 py-3 first:pt-0 last:pb-0">
                            <div class="min-w-0">
                                <a href="{{ route('admin.disputes.show', $dispute) }}" class="text-sm font-semibold text-slate-800 hover:text-primary dark:text-slate-100">
                                    #{{ str_pad((string) $dispute->id, 5, '0', STR_PAD_LEFT) }} · {{ $dispute->reasonLabel() }}
                                </a>
                                <p class="mt-0.5 truncate text-xs text-slate-500 dark:text-slate-400">
                                    {{ $dispute->raisedBy?->name ?? __('Unknown') }}
                                    @if ($dispute->against) → {{ $dispute->against->name }} @endif
                                    · {{ $dispute->created_at->diffForHumans() }}
                                </p>
                            </div>
                            <span class="shrink-0 rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $dispute->status->badgeClasses() }}">{{ $dispute->status->label() }}</span>
                        </li>
                    @empty
                        <li class="py-3 text-sm text-slate-500 dark:text-slate-400">{{ __('No disputes yet — the desk is clear.') }}</li>
                    @endforelse
                </ul>
            </div>

            <!-- Recent review flags -->
            <div class="{{ $cardBase }} hover:shadow-none" :class="{{ $cardTheme }}">
                <div class="flex items-center justify-between">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Reported reviews') }}</h3>
                    <a href="{{ route('admin.moderation.index') }}" class="text-xs font-semibold text-primary hover:underline">{{ __('Moderate →') }}</a>
                </div>

                <ul class="mt-4 space-y-3">
                    @forelse ($recentFlags as $review)
                        <li class="rounded-xl bg-slate-50 p-3 dark:bg-slate-800/40">
                            <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">
                                {{ $review->student?->name }} → {{ $review->teacherProfile?->user?->name }}
                            </p>
                            <p class="mt-1 text-sm text-slate-700 dark:text-slate-200">{{ $review->flagReasonLabel() }}</p>
                        </li>
                    @empty
                        <li class="text-sm text-slate-500 dark:text-slate-400">{{ __('Nothing reported.') }}</li>
                    @endforelse
                </ul>
            </div>
        </div>

        <!-- Environment card -->
        <div class="{{ $cardBase }} hover:shadow-none" :class="{{ $cardTheme }}">
            <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">Demo environment</h3>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">Reset the workspace with a full demo dataset at any time.</p>
            <div class="mt-4 rounded-xl bg-slate-900 p-4 font-mono text-xs text-slate-100 dark:bg-slate-950">
                php artisan migrate:fresh --seed
            </div>
            <ul class="mt-4 space-y-2 text-xs text-slate-500 dark:text-slate-400">
                <li class="flex items-center justify-between"><span>admin@studylikepro.test</span><span class="text-slate-400">admin</span></li>
                <li class="flex items-center justify-between"><span>teacher@studylikepro.test</span><span class="text-slate-400">teacher</span></li>
                <li class="flex items-center justify-between"><span>student@studylikepro.test</span><span class="text-slate-400">student</span></li>
            </ul>
            <p class="mt-3 text-xs text-slate-400 dark:text-slate-500">All demo accounts use the password <span class="font-semibold">password</span> (local only).</p>
        </div>

        <p class="text-center text-xs text-slate-400 dark:text-slate-500">
            <a href="{{ route('legal.privacy') }}" class="hover:text-primary">{{ __('Privacy policy') }}</a>
            · <a href="{{ route('legal.terms') }}" class="hover:text-primary">{{ __('Terms of service') }}</a>
            · <a href="{{ route('legal.refunds') }}" class="hover:text-primary">{{ __('Cancellation & refunds') }}</a>
            · <a href="{{ route('legal.contact') }}" class="hover:text-primary">{{ __('Contact support') }}</a>
        </p>
    </div>
</x-app-layout>
