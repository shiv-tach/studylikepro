@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $fieldClasses = 'rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';
    $chip = 'inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300';

    $tiles = [
        ['label' => __('Students'), 'value' => $counts['students'], 'tone' => 'text-slate-800 dark:text-slate-100'],
        ['label' => __('Teachers'), 'value' => $counts['teachers'], 'tone' => 'text-primary'],
        ['label' => __('Suspended'), 'value' => $counts['suspended'], 'tone' => $counts['suspended'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-500 dark:text-slate-400'],
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Users') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Every student and teacher on the platform.') }}</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6">
        <div class="grid gap-4 sm:grid-cols-3">
            @foreach ($tiles as $tile)
                <div class="{{ $cardBase }}" :class="{{ $cardTheme }}">
                    <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ $tile['label'] }}</p>
                    <p class="mt-2 text-2xl font-extrabold tracking-tight {{ $tile['tone'] }}">{{ $tile['value'] }}</p>
                </div>
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.users.index') }}" class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <div class="grid gap-4 sm:grid-cols-4">
                <div>
                    <x-input-label for="filter-role" :value="__('Role')" />
                    <select id="filter-role" name="role" class="mt-1 block w-full {{ $fieldClasses }}">
                        <option value="">{{ __('Any role') }}</option>
                        <option value="student" @selected(($filters['role'] ?? null) === 'student')>{{ __('Student') }}</option>
                        <option value="teacher" @selected(($filters['role'] ?? null) === 'teacher')>{{ __('Teacher') }}</option>
                    </select>
                </div>
                <div>
                    <x-input-label for="filter-status" :value="__('Status')" />
                    <select id="filter-status" name="status" class="mt-1 block w-full {{ $fieldClasses }}">
                        <option value="">{{ __('Any status') }}</option>
                        <option value="active" @selected(($filters['status'] ?? null) === 'active')>{{ __('Active') }}</option>
                        <option value="suspended" @selected(($filters['status'] ?? null) === 'suspended')>{{ __('Suspended') }}</option>
                    </select>
                </div>
                <div>
                    <x-input-label for="filter-search" :value="__('Name or email')" />
                    <x-text-input id="filter-search" name="search" type="text" class="mt-1 block w-full"
                                  :value="$filters['search'] ?? null" placeholder="{{ __('Search people') }}" />
                </div>
                <div class="flex items-end gap-3">
                    <x-primary-button>{{ __('Filter') }}</x-primary-button>
                    <a href="{{ route('admin.users.index') }}" class="text-sm font-semibold text-slate-500 hover:text-primary">{{ __('Reset') }}</a>
                </div>
            </div>
        </form>

        <div class="{{ $cardBase }} p-0" :class="{{ $cardTheme }}">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-400">
                            <th class="px-6 py-3">{{ __('Person') }}</th>
                            <th class="px-6 py-3">{{ __('Role') }}</th>
                            <th class="px-6 py-3">{{ __('Teacher status') }}</th>
                            <th class="px-6 py-3">{{ __('Joined') }}</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                        @forelse ($users as $user)
                            <tr>
                                <td class="px-6 py-4">
                                    <p class="font-semibold text-slate-800 dark:text-slate-100">{{ $user->name }}</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">{{ $user->email }}</p>
                                    @if ($user->isSuspended())
                                        <p class="mt-1 text-xs font-semibold text-rose-500">{{ __('Suspended: :reason', ['reason' => $user->suspension_reason]) }}</p>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    @foreach ($user->getRoleNames() as $role)
                                        <span class="{{ $chip }}">{{ ucfirst($role) }}</span>
                                    @endforeach
                                </td>
                                <td class="px-6 py-4">
                                    @if ($user->teacherProfile)
                                        <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $user->teacherProfile->verification_status->badgeClasses() }}">
                                            {{ $user->teacherProfile->verification_status->label() }}
                                        </span>
                                        <p class="mt-1 text-xs text-slate-400">
                                            ★ {{ number_format((float) $user->teacherProfile->rating_avg, 1) }} · {{ $user->teacherProfile->lessons_completed_count }} {{ __('lessons') }}
                                        </p>
                                    @else
                                        <span class="text-xs text-slate-400">—</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-slate-500 dark:text-slate-400">{{ $user->created_at->format('d M Y') }}</td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('admin.users.show', $user) }}" class="text-sm font-semibold text-primary hover:underline">{{ __('Open') }}</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('Nobody matches those filters.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{ $users->links() }}
    </div>
</x-app-layout>
