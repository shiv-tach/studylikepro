@php
    $user = Auth::user();
    $dashboardUrl = route($user->dashboardRoute());
    $onDashboard = request()->routeIs('dashboard') || request()->routeIs('*.dashboard');
    $profileUrl = match (true) {
        $user->isStudent() => route('student.profile'),
        $user->isTeacher() => route('teacher.profile'),
        default => route('profile.edit'),
    };
    $onProfile = request()->routeIs('profile.*') || request()->routeIs('student.profile') || request()->routeIs('teacher.profile');
    $roleLabel = match (true) {
        $user->isAdmin() => 'Administrator',
        $user->isTeacher() => 'Teacher',
        $user->isStudent() => 'Student',
        default => 'Member',
    };

    $pendingVerifications = $user->isAdmin()
        ? \App\Models\TeacherProfile::query()->where('verification_status', \App\Enums\VerificationStatus::Pending)->count()
        : null;

    $openDisputes = $user->isAdmin() ? \App\Models\Dispute::query()->open()->count() : null;

    $flaggedReviews = $user->isAdmin()
        ? \App\Models\Review::query()->flagged()->whereNull('hidden_at')->count()
        : null;

    $openRequestMatches = $user->isTeacher() && $user->teacherProfile?->isApproved()
        ? app(\App\Services\RequestMatcher::class)->openMatchCount($user->teacherProfile)
        : null;

    $upcomingLessons = $user->isStudent()
        ? $user->bookings()->upcoming()->whereIn('status', ['pending_payment', 'confirmed', 'in_progress'])->count()
        : null;

    $unpaidHolds = $user->isTeacher() && $user->teacherProfile
        ? \App\Models\Booking::query()
            ->where('teacher_profile_id', $user->teacherProfile->id)
            ->where('status', \App\Enums\BookingStatus::PendingPayment->value)
            ->where('expires_at', '>', now())
            ->count()
        : null;

    $availableEarnings = $user->isTeacher() && $user->teacherProfile
        ? app(\App\Services\Payments\EarningsService::class)->totalsFor($user->teacherProfile)['available']
        : 0;

    $navItems = [
        ['label' => 'Dashboard', 'url' => $dashboardUrl, 'active' => $onDashboard, 'icon' => 'dashboard', 'badge' => null],
    ];

    if ($user->isStudent()) {
        $navItems[] = ['label' => 'Subjects & lessons', 'url' => route('student.interests.edit'), 'active' => request()->routeIs('student.interests*'), 'icon' => 'book', 'badge' => null];
        $navItems[] = ['label' => 'My requests', 'url' => route('student.requests.index'), 'active' => request()->routeIs('student.requests*'), 'icon' => 'doc', 'badge' => null];
        $navItems[] = ['label' => 'My lessons', 'url' => route('student.bookings.index'), 'active' => request()->routeIs('student.bookings*'), 'icon' => 'calendar', 'badge' => $upcomingLessons ?: null];
        $navItems[] = ['label' => 'Messages', 'url' => route('messages.index'), 'active' => request()->routeIs('messages.*'), 'icon' => 'chat', 'badge' => ($unreadMessageCount ?? 0) ?: null];
        $navItems[] = ['label' => 'Find a teacher', 'url' => route('teachers.index'), 'active' => request()->routeIs('teachers.*'), 'icon' => 'search', 'badge' => null];
    }

    if ($user->isTeacher() && ! $user->hasCompletedOnboarding()) {
        // During onboarding only the two setup steps are reachable, so the sidebar
        // shows them instead of workspace links that would bounce back here.
        $navItems = [
            ['label' => 'Teaching profile', 'url' => route('teacher.profile'), 'active' => request()->routeIs('teacher.profile'), 'icon' => 'user', 'badge' => null],
            ['label' => 'Verification', 'url' => route('teacher.verification'), 'active' => request()->routeIs('teacher.verification*'), 'icon' => 'shield', 'badge' => null],
        ];
    } elseif ($user->isTeacher()) {
        $navItems[] = ['label' => 'Subjects & lessons', 'url' => route('teacher.subjects.index'), 'active' => request()->routeIs('teacher.subjects*'), 'icon' => 'book', 'badge' => null];
        $navItems[] = ['label' => 'Availability', 'url' => route('teacher.availability.index'), 'active' => request()->routeIs('teacher.availability*'), 'icon' => 'calendar', 'badge' => null];
        $navItems[] = ['label' => 'Requests', 'url' => route('teacher.requests.index'), 'active' => request()->routeIs('teacher.requests*'), 'icon' => 'doc', 'badge' => $openRequestMatches ?: null];
        $navItems[] = ['label' => 'Schedule', 'url' => route('teacher.schedule.index'), 'active' => request()->routeIs('teacher.schedule*') || request()->routeIs('teacher.bookings*'), 'icon' => 'clock', 'badge' => $unpaidHolds ?: null];
        $navItems[] = ['label' => 'Messages', 'url' => route('messages.index'), 'active' => request()->routeIs('messages.*'), 'icon' => 'chat', 'badge' => ($unreadMessageCount ?? 0) ?: null];
        $navItems[] = ['label' => 'Reviews', 'url' => route('teacher.reviews.index'), 'active' => request()->routeIs('teacher.reviews*'), 'icon' => 'star', 'badge' => ($user->teacherProfile?->rating_count ?? 0) ?: null];
        $navItems[] = ['label' => 'Earnings', 'url' => route('teacher.earnings.index'), 'active' => request()->routeIs('teacher.earnings*'), 'icon' => 'wallet', 'badge' => $availableEarnings > 0 ? platform_settings()->formatMinor($availableEarnings) : null];
    }

    if ($user->isAdmin()) {
        $navItems[] = ['label' => 'Reports', 'url' => route('admin.reports.index'), 'active' => request()->routeIs('admin.reports*'), 'icon' => 'chart', 'badge' => null];
        $navItems[] = ['label' => 'Users', 'url' => route('admin.users.index'), 'active' => request()->routeIs('admin.users*'), 'icon' => 'user', 'badge' => null];
        $navItems[] = ['label' => 'Invite teachers', 'url' => route('admin.invites.index'), 'active' => request()->routeIs('admin.invites*'), 'icon' => 'invite', 'badge' => null];
        $navItems[] = ['label' => 'Disputes', 'url' => route('admin.disputes.index'), 'active' => request()->routeIs('admin.disputes*'), 'icon' => 'shield', 'badge' => $openDisputes ?: null];
        $navItems[] = ['label' => 'Moderation', 'url' => route('admin.moderation.index'), 'active' => request()->routeIs('admin.moderation*'), 'icon' => 'flag', 'badge' => $flaggedReviews ?: null];
        $navItems[] = ['label' => 'Verification', 'url' => route('admin.verifications.index'), 'active' => request()->routeIs('admin.verifications*'), 'icon' => 'shield', 'badge' => $pendingVerifications ?: null];
        $navItems[] = ['label' => 'Curriculum', 'url' => route('admin.curriculum.index'), 'active' => request()->routeIs('admin.curriculum*'), 'icon' => 'book', 'badge' => null];
        $navItems[] = ['label' => 'Subjects', 'url' => route('admin.subjects.index'), 'active' => request()->routeIs('admin.subjects*'), 'icon' => 'book', 'badge' => null];
        $navItems[] = ['label' => 'Bookings', 'url' => route('admin.bookings.index'), 'active' => request()->routeIs('admin.bookings*'), 'icon' => 'calendar', 'badge' => null];
        $navItems[] = ['label' => 'Payments', 'url' => route('admin.payments.index'), 'active' => request()->routeIs('admin.payments*'), 'icon' => 'card', 'badge' => null];
        $navItems[] = ['label' => 'Payouts', 'url' => route('admin.payouts.index'), 'active' => request()->routeIs('admin.payouts*'), 'icon' => 'wallet', 'badge' => null];
        $navItems[] = ['label' => 'Special offers', 'url' => route('admin.offers.index'), 'active' => request()->routeIs('admin.offers*'), 'icon' => 'tag', 'badge' => null];
        $navItems[] = ['label' => 'Activity log', 'url' => route('admin.activity.index'), 'active' => request()->routeIs('admin.activity*'), 'icon' => 'clock', 'badge' => null];
        $navItems[] = ['label' => 'Settings', 'url' => route('admin.settings.edit'), 'active' => request()->routeIs('admin.settings*'), 'icon' => 'cog', 'badge' => null];
    }

    $navItems[] = ['label' => 'My Profile', 'url' => $profileUrl, 'active' => $onProfile, 'icon' => 'user', 'badge' => null];
    $navItems[] = ['label' => 'Settings', 'url' => route('settings.index'), 'active' => request()->routeIs('settings*'), 'icon' => 'cog', 'badge' => null];
@endphp

<!-- Mobile Sidebar Overlay -->
<div x-show="sidebarOpen"
     x-transition:enter="transition-opacity ease-linear duration-300"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition-opacity ease-linear duration-300"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-30 bg-slate-900/50 backdrop-blur-sm md:hidden"
     @click="sidebarOpen = false"
     style="display: none;"></div>

<!-- Sidebar Container -->
<aside :class="{
           'bg-white border-slate-200/80 dark:border-slate-800/80 dark:bg-slate-900': themePreset === 'classic' || themePreset === 'forest',
           'bg-slate-950 dark:bg-slate-950 border-slate-900 dark:border-slate-900/60 text-slate-100': themePreset === 'midnight',
           'bg-white/90 dark:bg-slate-900/85 border-slate-200/50 dark:border-slate-800/50': themePreset === 'sunset',
           'bg-white/70 dark:bg-slate-900/70 backdrop-blur-lg border-slate-200/40 dark:border-slate-800/40': themePreset === 'glass',
           'bg-white/80 dark:bg-slate-900/80 backdrop-blur-md border-cyan-200/60 dark:border-cyan-800/40': themePreset === 'ocean'
       }"
       :style="sidebarOpen ? 'transform: translateX(0)' : ''"
       class="fixed inset-y-0 left-0 z-40 flex w-64 flex-col border-r transition-all duration-300 ease-in-out -translate-x-full md:translate-x-0">

    <!-- Sidebar Header (Logo and Close) -->
    <div class="flex h-16 items-center justify-between px-6 border-b border-slate-200/50 dark:border-slate-800/50"
         :class="{'border-slate-800/80': themePreset === 'midnight', 'border-cyan-200/50 dark:border-cyan-800/30': themePreset === 'ocean'}">
        <a href="{{ $dashboardUrl }}" class="flex items-center gap-2.5 font-bold text-lg text-primary">
            <x-application-logo class="h-8 w-8 text-primary" />
            <span class="tracking-tight" :class="{'text-slate-100': themePreset === 'midnight'}">Studylikepro</span>
        </a>

        <!-- Mobile close button -->
        <button @click="sidebarOpen = false" class="rounded-lg p-1 text-slate-500 hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 md:hidden">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <!-- Navigation List -->
    <nav class="flex-1 space-y-1.5 px-4 py-6 overflow-y-auto">
        @foreach ($navItems as $item)
            <a href="{{ $item['url'] }}"
               class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium transition-all"
               :class="{
                   'text-primary bg-primary/10 dark:bg-primary/20 shadow-sm shadow-primary/5': {{ $item['active'] ? 'true' : 'false' }},
                   'text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800/50': !{{ $item['active'] ? 'true' : 'false' }} && themePreset !== 'midnight',
                   'text-slate-300 hover:bg-slate-800 hover:text-white': !{{ $item['active'] ? 'true' : 'false' }} && themePreset === 'midnight'
               }">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    @switch($item['icon'])
                        @case('dashboard')
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z" />
                            @break
                        @case('book')
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                            @break
                        @case('shield')
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z" />
                            @break
                        @case('calendar')
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                            @break
                        @case('search')
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                            @break
                        @case('doc')
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            @break
                        @case('clock')
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                            @break
                        @case('card')
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                            @break
                        @case('wallet')
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-2m0-6h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a3 3 0 010-6z" />
                            @break
                        @case('chat')
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
                            @break
                        @case('star')
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.977-2.888a1 1 0 00-1.175 0l-3.976 2.888c-.783.57-1.838-.196-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z" />
                            @break
                        @case('user')
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                            @break
                        @case('invite')
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m.758-10.556a4 4 0 015.656 0l4 4a4 4 0 01-5.656 5.656l-1.1-1.1" />
                            @break
                        @case('cog')
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            @break
                        @case('chart')
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                            @break
                        @case('flag')
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9" />
                            @break
                        @case('tag')
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 12V7a4 4 0 014-4z" />
                            @break
                    @endswitch
                </svg>
                <span>{{ $item['label'] }}</span>
                @if (! empty($item['badge']))
                    <span class="ml-auto inline-flex min-w-5 items-center justify-center rounded-full bg-primary px-1.5 py-0.5 text-[10px] font-bold text-white">{{ $item['badge'] }}</span>
                @endif
            </a>
        @endforeach
    </nav>

    <!-- Sidebar Footer -->
    <div class="border-t border-slate-200/50 p-4 dark:border-slate-800/50"
         :class="{'border-slate-800/80': themePreset === 'midnight', 'border-cyan-200/50 dark:border-cyan-800/30': themePreset === 'ocean'}">
        <div class="flex items-center gap-3 rounded-xl bg-slate-50 p-3 dark:bg-slate-800/30"
             :class="{'bg-slate-900/60': themePreset === 'midnight'}">
            @if ($user->avatarUrl())
                <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}" class="h-9 w-9 rounded-xl object-cover shadow-md" />
            @else
                <div class="h-9 w-9 rounded-xl bg-primary text-white flex items-center justify-center font-bold text-sm shadow-md shadow-primary/20">
                    {{ substr($user->name, 0, 2) }}
                </div>
            @endif
            <div class="flex-1 overflow-hidden">
                <h4 class="truncate text-sm font-semibold text-slate-700 dark:text-slate-300" :class="{'text-slate-200': themePreset === 'midnight'}">{{ $user->name }}</h4>
                <div class="mt-0.5 flex items-center gap-1.5">
                    <span class="inline-flex items-center rounded-full bg-primary/10 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-primary">{{ $roleLabel }}</span>
                </div>
            </div>
        </div>
    </div>
</aside>
