@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $chip = 'inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300';
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);
    $teacher = $user->teacherProfile;
@endphp

<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-wrap items-center gap-3">
            <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ $user->name }}</h2>
            @if ($user->isSuspended())
                <span class="inline-flex items-center rounded-full bg-rose-100 px-2.5 py-0.5 text-[11px] font-semibold text-rose-700 dark:bg-rose-900/30 dark:text-rose-400">{{ __('Suspended') }}</span>
            @else
                <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">{{ __('Active') }}</span>
            @endif
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6">
        <a href="{{ route('admin.users.index') }}" class="inline-flex items-center gap-1.5 text-sm font-semibold text-slate-500 transition-colors hover:text-primary dark:text-slate-400">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
            </svg>
            {{ __('Back to users') }}
        </a>

        @php
            $flashes = [
                'user-suspended' => __('Account suspended — they can no longer sign in.'),
                'user-reactivated' => __('Account reactivated — they can sign in again.'),
                'teacher-reverified' => __('Teacher sent back to verification.'),
                'notification-resent' => __('Notification sent again.'),
            ];
        @endphp

        @if (isset($flashes[session('status')]))
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                {{ $flashes[session('status')] }}
            </div>
        @endif

        <div class="grid gap-6 lg:grid-cols-3">
            <!-- Identity -->
            <div class="{{ $cardBase }} lg:col-span-2" :class="{{ $cardTheme }}">
                <div class="flex items-center gap-4">
                    @if ($user->avatarUrl())
                        <img src="{{ $user->avatarUrl() }}" alt="{{ $user->name }}" class="h-14 w-14 rounded-2xl object-cover shadow-md" />
                    @else
                        <div class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary text-lg font-bold text-white shadow-md shadow-primary/20">
                            {{ substr($user->name, 0, 2) }}
                        </div>
                    @endif
                    <div>
                        <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ $user->name }}</h3>
                        <p class="text-sm text-slate-400">{{ $user->email }}</p>
                        <div class="mt-1 flex flex-wrap gap-1.5">
                            @foreach ($user->getRoleNames() as $role)
                                <span class="{{ $chip }}">{{ ucfirst($role) }}</span>
                            @endforeach
                            <span class="{{ $chip }}">{{ __('Joined :date', ['date' => $user->created_at->format('d M Y')]) }}</span>
                        </div>
                    </div>
                </div>

                @if ($user->isSuspended())
                    <p class="mt-5 rounded-xl bg-rose-50 p-3 text-sm text-rose-700 dark:bg-rose-950/30 dark:text-rose-300">
                        {{ __('Suspended on :date — :reason', ['date' => $user->suspended_at->format('d M Y'), 'reason' => $user->suspension_reason]) }}
                    </p>
                @endif

                @if ($teacher)
                    <dl class="mt-6 grid gap-5 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Verification') }}</dt>
                            <dd class="mt-1">
                                <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $teacher->verification_status->badgeClasses() }}">{{ $teacher->verification_status->label() }}</span>
                                @if ($teacher->verification_notes)
                                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ $teacher->verification_notes }}</p>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Rating') }}</dt>
                            <dd class="mt-1 text-sm text-slate-700 dark:text-slate-200">
                                ★ {{ number_format((float) $teacher->rating_avg, 1) }} ({{ $teacher->rating_count }})
                                · {{ $teacher->lessons_completed_count }} {{ __('lessons') }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Rate') }}</dt>
                            <dd class="mt-1 text-sm text-slate-700 dark:text-slate-200">{{ $money($teacher->hourly_rate_minor) }} / {{ __('hour') }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Balances') }}</dt>
                            <dd class="mt-1 text-sm text-slate-700 dark:text-slate-200">
                                {{ __(':available available · :pending pending', [
                                    'available' => $money($teacher->availableBalanceMinor()),
                                    'pending' => $money($teacher->pendingBalanceMinor()),
                                ]) }}
                            </dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Subjects') }}</dt>
                            <dd class="mt-1 flex flex-wrap gap-1.5">
                                @forelse ($teacher->subjects as $subject)
                                    <span class="{{ $chip }}">{{ $subject->name }}</span>
                                @empty
                                    <span class="text-xs text-slate-400">{{ __('None selected yet') }}</span>
                                @endforelse
                            </dd>
                        </div>
                    </dl>
                @elseif ($user->studentProfile)
                    <dl class="mt-6 grid gap-5 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Grade') }}</dt>
                            <dd class="mt-1 text-sm text-slate-700 dark:text-slate-200">{{ $user->studentProfile->grade_level ?: '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Timezone') }}</dt>
                            <dd class="mt-1 text-sm text-slate-700 dark:text-slate-200">{{ $user->studentProfile->timezone ?: '—' }}</dd>
                        </div>
                        <div class="sm:col-span-2">
                            <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ __('Learning goals') }}</dt>
                            <dd class="mt-1 text-sm text-slate-700 dark:text-slate-200">{{ $user->studentProfile->learning_goals ?: '—' }}</dd>
                        </div>
                    </dl>
                @endif
            </div>

            <!-- Actions -->
            <div class="space-y-6">
                <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Account') }}</h3>

                    @if ($user->isSuspended())
                        <p class="mt-2 text-xs text-slate-500 dark:text-slate-400">{{ __('Reactivating lets them sign in again straight away.') }}</p>
                        <form method="POST" action="{{ route('admin.users.reactivate', $user) }}" class="mt-4">
                            @csrf
                            <x-primary-button>{{ __('Reactivate account') }}</x-primary-button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('admin.users.suspend', $user) }}" class="mt-4 space-y-3">
                            @csrf
                            <div>
                                <x-input-label for="suspension-reason" :value="__('Suspension reason')" />
                                <textarea id="suspension-reason" name="reason" rows="2" required
                                          class="mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">{{ old('reason') }}</textarea>
                                @error('reason')
                                    <p class="mt-1 text-xs text-rose-500">{{ $message }}</p>
                                @enderror
                            </div>
                            <button type="submit"
                                    class="inline-flex items-center rounded-xl bg-rose-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-rose-700">
                                {{ __('Suspend account') }}
                            </button>
                        </form>
                    @endif
                </div>

                @if ($teacher)
                    <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                        <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Verification') }}</h3>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ __('Send this teacher back through review if their credentials need another look.') }}</p>

                        <form method="POST" action="{{ route('admin.users.reverify', $user) }}" class="mt-4 space-y-3">
                            @csrf
                            <textarea name="notes" rows="2" required placeholder="{{ __('What needs checking?') }}"
                                      class="block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">{{ old('notes') }}</textarea>
                            @error('notes')
                                <p class="text-xs text-rose-500">{{ $message }}</p>
                            @enderror
                            <button type="submit"
                                    class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition-colors hover:border-primary hover:text-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                {{ __('Send back for verification') }}
                            </button>
                        </form>
                    </div>
                @endif

                <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Notifications') }}</h3>
                    <ul class="mt-3 space-y-2 text-xs text-slate-500 dark:text-slate-400">
                        @forelse ($notifications as $notification)
                            <li class="rounded-xl bg-slate-50 p-2.5 dark:bg-slate-800/40">
                                <p class="font-semibold text-slate-700 dark:text-slate-200">{{ data_get($notification->data, 'title') }}</p>
                                <p class="mt-0.5">{{ $notification->created_at->diffForHumans() }}</p>
                            </li>
                        @empty
                            <li>{{ __('No notifications yet.') }}</li>
                        @endforelse
                    </ul>

                    @if ($notifications->isNotEmpty())
                        <form method="POST" action="{{ route('admin.users.resend-notification', $user) }}" class="mt-4">
                            @csrf
                            <button type="submit"
                                    class="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm transition-colors hover:border-primary hover:text-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                                {{ __('Resend the latest') }}
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        </div>

        <!-- Lessons -->
        <div class="{{ $cardBase }} p-0" :class="{{ $cardTheme }}">
            <div class="border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Recent lessons') }}</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-400">
                            <th class="px-6 py-3">{{ __('Lesson') }}</th>
                            <th class="px-6 py-3">{{ __('With') }}</th>
                            <th class="px-6 py-3">{{ __('When') }}</th>
                            <th class="px-6 py-3">{{ __('Status') }}</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                        @forelse ($bookings as $booking)
                            <tr>
                                <td class="px-6 py-4 font-semibold text-slate-800 dark:text-slate-100">
                                    #{{ str_pad((string) $booking->id, 6, '0', STR_PAD_LEFT) }}
                                    <p class="text-xs font-normal text-slate-500 dark:text-slate-400">{{ $booking->subject?->name }}@if ($booking->topic) · {{ $booking->topic->name }}@endif</p>
                                </td>
                                <td class="px-6 py-4 text-slate-600 dark:text-slate-300">
                                    {{ $user->isTeacher() ? $booking->student->name : $booking->teacherProfile->user->name }}
                                </td>
                                <td class="px-6 py-4 text-slate-600 dark:text-slate-300">{{ $booking->starts_at->copy()->setTimezone($timezone)->format('d M Y, H:i') }}</td>
                                <td class="px-6 py-4">
                                    <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $booking->status->badgeClasses() }}">{{ $booking->status->label() }}</span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('admin.bookings.show', $booking) }}" class="text-sm font-semibold text-primary hover:underline">{{ __('Open') }}</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('No lessons yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <!-- Payments -->
            <div class="{{ $cardBase }} p-0" :class="{{ $cardTheme }}">
                <div class="border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Recent payments') }}</h3>
                </div>
                <ul class="divide-y divide-slate-100 text-sm dark:divide-slate-800/60">
                    @forelse ($payments as $payment)
                        <li class="flex items-center justify-between gap-3 px-6 py-3">
                            <div>
                                <a href="{{ route('admin.payments.show', $payment) }}" class="font-semibold text-slate-800 hover:text-primary dark:text-slate-100">
                                    {{ $money($payment->amount_minor) }}
                                </a>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $payment->gateway }} · {{ $payment->created_at->format('d M Y') }}
                                </p>
                            </div>
                            <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $payment->status->badgeClasses() }}">{{ $payment->status->label() }}</span>
                        </li>
                    @empty
                        <li class="px-6 py-6 text-sm text-slate-500 dark:text-slate-400">{{ __('No payments yet.') }}</li>
                    @endforelse
                </ul>
            </div>

            <!-- Reviews & reports -->
            <div class="{{ $cardBase }} p-0" :class="{{ $cardTheme }}">
                <div class="border-b border-slate-100 px-6 py-4 dark:border-slate-800">
                    <h3 class="text-base font-bold text-slate-800 dark:text-slate-200">{{ __('Reviews & reports') }}</h3>
                </div>
                <ul class="divide-y divide-slate-100 text-sm dark:divide-slate-800/60">
                    @forelse ($reviews as $review)
                        <li class="px-6 py-3">
                            <p class="font-semibold text-slate-800 dark:text-slate-100">
                                ★ {{ $review->rating }}
                                · {{ $user->isTeacher() ? $review->student->name : $review->teacherProfile->user->name }}
                            </p>
                            <p class="text-xs text-slate-500 dark:text-slate-400">{{ $review->comment ?: __('No comment') }}</p>
                            @if ($review->hidden_at)
                                <p class="mt-1 text-xs font-semibold text-slate-500 dark:text-slate-400">{{ __('Hidden by support') }}</p>
                            @elseif ($review->flagged_at)
                                <p class="mt-1 text-xs font-semibold text-amber-600 dark:text-amber-400">{{ __('Reported: :reason', ['reason' => $review->flagReasonLabel()]) }}</p>
                            @endif
                        </li>
                    @empty
                        <li class="px-6 py-4 text-sm text-slate-500 dark:text-slate-400">{{ __('No reviews yet.') }}</li>
                    @endforelse

                    @foreach ($disputes as $dispute)
                        <li class="flex items-center justify-between gap-3 px-6 py-3">
                            <div>
                                <a href="{{ route('admin.disputes.show', $dispute) }}" class="font-semibold text-slate-800 hover:text-primary dark:text-slate-100">
                                    {{ __('Dispute #:id', ['id' => $dispute->id]) }}
                                </a>
                                <p class="text-xs text-slate-500 dark:text-slate-400">
                                    {{ $dispute->raised_by === $user->id ? __('Raised by this person') : __('Reported about this person') }}
                                    · {{ $dispute->created_at->format('d M Y') }}
                                </p>
                            </div>
                            <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $dispute->status->badgeClasses() }}">{{ $dispute->status->label() }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</x-app-layout>
