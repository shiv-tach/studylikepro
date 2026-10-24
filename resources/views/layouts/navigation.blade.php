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

    $pendingSubjectRequests = $user->isAdmin()
        ? \App\Models\SubjectRequest::query()->where('status', \App\Enums\ApprovalStatus::Pending)->count()
        : null;

    $openDisputes = $user->isAdmin() ? \App\Models\Dispute::query()->open()->count() : null;

    $flaggedReviews = $user->isAdmin()
        ? \App\Models\Review::query()->flagged()->whereNull('hidden_at')->count()
        : null;

    $upcomingLessons = $user->isStudent()
        ? $user->bookings()->upcoming()->whereIn('status', ['pending_payment', 'confirmed', 'in_progress'])->count()
        : null;

    // Lessons still waiting to be taught, matching the agenda the schedule
    // page opens with (live lessons included).
    $lessonsToTeach = $user->isTeacher() && $user->teacherProfile
        ? $user->teacherProfile->bookings()
            ->whereIn('status', [\App\Enums\BookingStatus::Confirmed->value, \App\Enums\BookingStatus::InProgress->value])
            ->where('starts_at', '>=', now()->subHour())
            ->count()
        : null;

    $availableEarnings = $user->isTeacher() && $user->teacherProfile
        ? app(\App\Services\Payments\EarningsService::class)->totalsFor($user->teacherProfile)['available']
        : 0;

    // Papers waiting for the teacher's pen. Paid-only, so a checkout that never
    // completed never inflates the sidebar.
    $papersToMark = $user->isTeacher() && $user->teacherProfile
        ? \App\Models\PaperMarkingOrder::query()
            ->forTeacher($user->teacherProfile->id)
            ->paid()
            ->awaitingWork()
            ->count()
        : 0;

    $navItems = [
        ['label' => 'Dashboard', 'url' => $dashboardUrl, 'active' => $onDashboard, 'icon' => 'dashboard', 'badge' => null],
    ];

    if ($user->isStudent() && ! $user->hasCompletedOnboarding()) {
        // The wizard is the only page a new student can reach, so the sidebar
        // shows that one step instead of links that would bounce back here.
        $navItems = [
            ['label' => 'Get started', 'url' => route('student.onboarding.show'), 'active' => request()->routeIs('student.onboarding*'), 'icon' => 'user', 'badge' => null],
        ];
    } elseif ($user->isStudent()) {
        $navItems[] = ['label' => 'My subjects', 'url' => route('student.interests.edit'), 'active' => request()->routeIs('student.interests*'), 'icon' => 'book', 'badge' => null];
        $navItems[] = ['label' => 'My lessons', 'url' => route('student.bookings.index'), 'active' => request()->routeIs('student.bookings*'), 'icon' => 'calendar', 'badge' => $upcomingLessons ?: null];
        $navItems[] = ['label' => 'My purchases', 'url' => route('student.purchases.index'), 'active' => request()->routeIs('student.purchases*') || request()->routeIs('student.purchase-sessions*') || request()->routeIs('student.marking*'), 'icon' => 'wallet', 'badge' => null];
        $navItems[] = ['label' => 'Messages', 'url' => route('messages.index'), 'active' => request()->routeIs('messages.*'), 'icon' => 'chat', 'badge' => ($unreadMessageCount ?? 0) ?: null];
        $navItems[] = ['label' => 'Find a teacher', 'url' => route('student.teachers.index'), 'active' => request()->routeIs('student.teachers*', 'teachers.*'), 'icon' => 'search', 'badge' => null];
    }

    if ($user->isTeacher() && ! $user->hasCompletedOnboarding()) {
        // During onboarding only the three setup steps are reachable, so the
        // sidebar shows them instead of workspace links that would bounce back
        // here. The order mirrors App\Support\TeacherOnboarding.
        $navItems = [
            ['label' => 'Teaching profile', 'url' => route('teacher.profile'), 'active' => request()->routeIs('teacher.profile'), 'icon' => 'user', 'badge' => null],
            ['label' => 'Weekly availability', 'url' => route('teacher.availability.setup'), 'active' => request()->routeIs('teacher.availability.setup'), 'icon' => 'calendar', 'badge' => null],
            ['label' => 'Verification', 'url' => route('teacher.verification'), 'active' => request()->routeIs('teacher.verification*'), 'icon' => 'shield', 'badge' => null],
        ];
    } elseif ($user->isTeacher()) {
        $navItems[] = ['label' => 'Orders', 'url' => route('teacher.orders.index'), 'active' => request()->routeIs('teacher.orders*'), 'icon' => 'wallet', 'badge' => null];
        $navItems[] = ['label' => 'My lessons', 'url' => route('teacher.schedule.index'), 'active' => request()->routeIs('teacher.schedule*') || request()->routeIs('teacher.bookings*'), 'icon' => 'clock', 'badge' => $lessonsToTeach ?: null];
        $navItems[] = ['label' => 'Paper Marking', 'url' => route('teacher.marking.index'), 'active' => request()->routeIs('teacher.marking*'), 'icon' => 'check', 'badge' => $papersToMark ?: null];
        $navItems[] = ['label' => 'Earnings', 'url' => route('teacher.earnings.index'), 'active' => request()->routeIs('teacher.earnings*'), 'icon' => 'wallet', 'badge' => $availableEarnings > 0 ? platform_settings()->formatMinor($availableEarnings) : null];
        $navItems[] = ['label' => 'Messages', 'url' => route('messages.index'), 'active' => request()->routeIs('messages.*'), 'icon' => 'chat', 'badge' => ($unreadMessageCount ?? 0) ?: null];
        $navItems[] = ['label' => 'Services & pricing', 'url' => route('teacher.services.index'), 'active' => request()->routeIs('teacher.services*'), 'icon' => 'tag', 'badge' => null];
        $navItems[] = ['label' => 'Subjects', 'url' => route('teacher.subjects.index'), 'active' => request()->routeIs('teacher.subjects*'), 'icon' => 'book', 'badge' => null];
        $navItems[] = ['label' => 'Availability', 'url' => route('teacher.availability.index'), 'active' => request()->routeIs('teacher.availability*'), 'icon' => 'calendar', 'badge' => null];
        $navItems[] = ['label' => 'Reviews', 'url' => route('teacher.reviews.index'), 'active' => request()->routeIs('teacher.reviews*'), 'icon' => 'star', 'badge' => null];
    }

    if ($user->isAdmin()) {
        $navItems[] = ['label' => 'Reports', 'url' => route('admin.reports.index'), 'active' => request()->routeIs('admin.reports*'), 'icon' => 'chart', 'badge' => null];
        $navItems[] = ['label' => 'Earnings', 'url' => route('admin.earnings.index'), 'active' => request()->routeIs('admin.earnings*'), 'icon' => 'coins', 'badge' => null];
        $navItems[] = ['label' => 'Users', 'url' => route('admin.users.index'), 'active' => request()->routeIs('admin.users*'), 'icon' => 'user', 'badge' => null];
        $navItems[] = ['label' => 'Invite teachers', 'url' => route('admin.invites.index'), 'active' => request()->routeIs('admin.invites*'), 'icon' => 'invite', 'badge' => null];
        $navItems[] = ['label' => 'Disputes', 'url' => route('admin.disputes.index'), 'active' => request()->routeIs('admin.disputes*'), 'icon' => 'shield', 'badge' => $openDisputes ?: null];
        $navItems[] = ['label' => 'Moderation', 'url' => route('admin.moderation.index'), 'active' => request()->routeIs('admin.moderation*'), 'icon' => 'flag', 'badge' => $flaggedReviews ?: null];
        $navItems[] = ['label' => 'Verification', 'url' => route('admin.verifications.index'), 'active' => request()->routeIs('admin.verifications*'), 'icon' => 'shield', 'badge' => $pendingVerifications ?: null];
        $navItems[] = ['label' => 'Subject requests', 'url' => route('admin.subject-requests.index'), 'active' => request()->routeIs('admin.subject-requests*'), 'icon' => 'book', 'badge' => $pendingSubjectRequests ?: null];
        $navItems[] = ['label' => 'Curriculum', 'url' => route('admin.curriculum.index'), 'active' => request()->routeIs('admin.curriculum*'), 'icon' => 'book', 'badge' => null];
        $navItems[] = ['label' => 'Subjects', 'url' => route('admin.subjects.index'), 'active' => request()->routeIs('admin.subjects*'), 'icon' => 'book', 'badge' => null];
        $navItems[] = ['label' => 'Bookings', 'url' => route('admin.bookings.index'), 'active' => request()->routeIs('admin.bookings*'), 'icon' => 'calendar', 'badge' => null];
        $navItems[] = ['label' => 'Services', 'url' => route('admin.services.index'), 'active' => request()->routeIs('admin.services*'), 'icon' => 'tag', 'badge' => null];
        $navItems[] = ['label' => 'Purchases', 'url' => route('admin.purchases.index'), 'active' => request()->routeIs('admin.purchases*'), 'icon' => 'wallet', 'badge' => null];
        $navItems[] = ['label' => 'Marking', 'url' => route('admin.marking.index'), 'active' => request()->routeIs('admin.marking*'), 'icon' => 'check', 'badge' => null];
        $navItems[] = ['label' => 'Payments', 'url' => route('admin.payments.index'), 'active' => request()->routeIs('admin.payments*'), 'icon' => 'card', 'badge' => null];
        $navItems[] = ['label' => 'Payouts', 'url' => route('admin.payouts.index'), 'active' => request()->routeIs('admin.payouts*'), 'icon' => 'wallet', 'badge' => null];
        $navItems[] = ['label' => 'Special offers', 'url' => route('admin.offers.index'), 'active' => request()->routeIs('admin.offers*'), 'icon' => 'tag', 'badge' => null];
        $navItems[] = ['label' => 'Activity log', 'url' => route('admin.activity.index'), 'active' => request()->routeIs('admin.activity*'), 'icon' => 'clock', 'badge' => null];
        $navItems[] = ['label' => 'Settings', 'url' => route('admin.settings.edit'), 'active' => request()->routeIs('admin.settings*'), 'icon' => 'cog', 'badge' => null];
    }

    $navItems[] = ['label' => 'My Profile', 'url' => $profileUrl, 'active' => $onProfile, 'icon' => 'user', 'badge' => null];
    $navItems[] = ['label' => 'Settings', 'url' => route('settings.index'), 'active' => request()->routeIs('settings*'), 'icon' => 'cog', 'badge' => null];

    // The bell's rows, flattened so the JSON island stays decoupled from the
    // notification record shape.
    $notificationItems = collect($recentNotifications ?? [])->map(fn ($notification) => [
        'id' => $notification->id,
        'title' => (string) data_get($notification->data, 'title', 'Notification'),
        'body' => \Illuminate\Support\Str::limit((string) data_get($notification->data, 'body', ''), 110),
        'time' => $notification->created_at->diffForHumans(),
        'unread' => $notification->read_at === null,
    ])->values()->all();

    // Everything the Vue shell needs. Blade keeps ownership of the route map so
    // no URL is ever hardcoded in resources/js/app-shell.
    $shell = [
        'appName' => config('app.name', 'Studylikepro'),
        'logoUrl' => asset('images/brand/logo-mark.png'),
        'csrfToken' => csrf_token(),
        'user' => [
            'name' => $user->name,
            'initials' => mb_substr($user->name, 0, 2),
            'roleLabel' => $roleLabel,
            'avatarUrl' => $user->avatarUrl(),
        ],
        'navItems' => $navItems,
        'notifications' => [
            'unreadCount' => (int) ($unreadNotificationCount ?? 0),
            'items' => $notificationItems,
        ],
        'theme' => [
            'preset' => $userTheme['preset'] ?? 'classic',
            'accent' => $userTheme['accent'] ?? 'indigo',
            'mode' => $userTheme['mode'] ?? 'light',
        ],
        'urls' => [
            'dashboard' => $dashboardUrl,
            'notifications' => route('notifications.index'),
            'readAll' => route('notifications.read-all'),
            // A placeholder keeps the id out of the route map; the bell swaps it
            // for the real notification id as it renders each row.
            'notificationOpen' => route('notifications.open', ['notification' => 'NOTIFICATION_ID']),
            'profile' => route('profile.edit'),
            'settings' => route('settings.index'),
            'logout' => route('logout'),
        ],
    ];

    // JSON_UNESCAPED_SLASHES keeps the URLs readable; the HEX flags keep the
    // island safe inside a <script> tag.
    $jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES;
@endphp

{{-- Mount point for resources/js/app-shell. The JSON island and the page-header
     template sit inside it so Vue removes them from the DOM once it mounts; the
     layout's <noscript> block keeps the page usable without JavaScript. --}}
{{-- Mount point for resources/js/app-shell. The JSON island and the page-header
     template sit inside it so Vue removes them from the DOM once it mounts. The
     wrapper is `display: contents`, so the sticky top bar keeps the content
     column as its containing block and behaves exactly as it did in Blade. --}}
<div id="studylikepro-shell" class="contents">
    <script type="application/json" id="studylikepro-shell-config">@json($shell, $jsonFlags)</script>
    <template id="studylikepro-page-header">@isset($header){{ $header }}@endisset</template>
</div>

{{-- Without JavaScript the Vue shell cannot render, so fall back to plain
     navigation links instead of an empty chrome. --}}
<noscript>
    <div class="flex flex-wrap items-center gap-2 border-b border-slate-200/80 bg-white p-4 dark:border-slate-800/80 dark:bg-slate-900">
        @foreach ($navItems as $item)
            <a href="{{ $item['url'] }}" class="rounded-xl px-3 py-2 text-sm font-medium text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800">
                {{ $item['label'] }}
            </a>
        @endforeach
    </div>
</noscript>
