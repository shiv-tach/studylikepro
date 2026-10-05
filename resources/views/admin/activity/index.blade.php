@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $fieldClasses = 'rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Activity log') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Every change an admin made in the console, newest first.') }}</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6">
        <form method="GET" action="{{ route('admin.activity.index') }}" class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <div class="grid gap-4 sm:grid-cols-5">
                <div>
                    <x-input-label for="filter-user" :value="__('Admin')" />
                    <select id="filter-user" name="user" class="mt-1 block w-full {{ $fieldClasses }}">
                        <option value="">{{ __('Anyone') }}</option>
                        @foreach ($admins as $admin)
                            <option value="{{ $admin->id }}" @selected(($filters['user'] ?? null) == $admin->id)>{{ $admin->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="filter-action" :value="__('Action')" />
                    <select id="filter-action" name="action" class="mt-1 block w-full {{ $fieldClasses }}">
                        <option value="">{{ __('Any action') }}</option>
                        @foreach ($actions as $action)
                            <option value="{{ $action }}" @selected(($filters['action'] ?? null) === $action)>{{ $action }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="filter-from" :value="__('From')" />
                    <x-text-input id="filter-from" name="from" type="date" class="mt-1 block w-full" :value="$filters['from'] ?? null" />
                </div>
                <div>
                    <x-input-label for="filter-to" :value="__('To')" />
                    <x-text-input id="filter-to" name="to" type="date" class="mt-1 block w-full" :value="$filters['to'] ?? null" />
                </div>
                <div class="flex items-end gap-3">
                    <x-primary-button>{{ __('Filter') }}</x-primary-button>
                    <a href="{{ route('admin.activity.index') }}" class="text-sm font-semibold text-slate-500 hover:text-primary">{{ __('Reset') }}</a>
                </div>
            </div>

            <div class="mt-4">
                <x-input-label for="filter-search" :value="__('Search the description')" />
                <x-text-input id="filter-search" name="search" type="text" class="mt-1 block w-full" :value="$filters['search'] ?? null" placeholder="{{ __('e.g. suspended, refund, dispute') }}" />
            </div>
        </form>

        <div class="{{ $cardBase }} p-0" :class="{{ $cardTheme }}">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-400">
                            <th class="px-6 py-3">{{ __('When') }}</th>
                            <th class="px-6 py-3">{{ __('Admin') }}</th>
                            <th class="px-6 py-3">{{ __('What happened') }}</th>
                            <th class="px-6 py-3">{{ __('Action') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                        @forelse ($entries as $entry)
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-slate-500 dark:text-slate-400">
                                    {{ $entry->created_at->format('d M Y, H:i') }}
                                    <p class="text-xs text-slate-400">{{ $entry->created_at->diffForHumans() }}</p>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="font-semibold text-slate-800 dark:text-slate-100">{{ $entry->user?->name ?? __('System') }}</p>
                                    <p class="text-xs text-slate-400">{{ $entry->ip_address }}</p>
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-slate-700 dark:text-slate-200">{{ $entry->description ?: $entry->actionLabel() }}</p>
                                    @if ($entry->subject_type)
                                        <p class="text-xs text-slate-400">{{ class_basename($entry->subject_type) }} #{{ $entry->subject_id }}</p>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">{{ $entry->actionLabel() }}</span>
                                    @if (data_get($entry->properties, 'input'))
                                        <details class="mt-2 text-xs text-slate-500 dark:text-slate-400">
                                            <summary class="cursor-pointer font-semibold text-primary">{{ __('Input') }}</summary>
                                            <pre class="mt-1 max-w-sm overflow-auto rounded-lg bg-slate-900 p-2 font-mono text-[11px] text-slate-100 dark:bg-slate-950">{{ json_encode(data_get($entry->properties, 'input'), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                        </details>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('Nothing logged in this range yet.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{ $entries->links() }}
    </div>
</x-app-layout>
