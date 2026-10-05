@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $fieldClasses = 'rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';

    $tiles = [
        ['label' => __('Open'), 'value' => $counts['open'], 'tone' => $counts['open'] > 0 ? 'text-rose-600 dark:text-rose-400' : 'text-slate-500 dark:text-slate-400'],
        ['label' => __('Resolved'), 'value' => $counts['resolved'], 'tone' => 'text-emerald-600 dark:text-emerald-400'],
        ['label' => __('Dismissed'), 'value' => $counts['dismissed'], 'tone' => 'text-slate-500 dark:text-slate-400'],
    ];
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Disputes') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Reports raised from the lesson chat, and what support decided.') }}</p>
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

        <form method="GET" action="{{ route('admin.disputes.index') }}" class="{{ $cardBase }}" :class="{{ $cardTheme }}">
            <div class="grid gap-4 sm:grid-cols-4">
                <div>
                    <x-input-label for="filter-status" :value="__('Status')" />
                    <select id="filter-status" name="status" class="mt-1 block w-full {{ $fieldClasses }}">
                        <option value="">{{ __('Any status') }}</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected(($filters['status'] ?? null) === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="filter-reason" :value="__('Reason')" />
                    <select id="filter-reason" name="reason" class="mt-1 block w-full {{ $fieldClasses }}">
                        <option value="">{{ __('Any reason') }}</option>
                        @foreach ($reasons as $value => $label)
                            <option value="{{ $value }}" @selected(($filters['reason'] ?? null) === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <x-input-label for="filter-search" :value="__('Person or lesson')" />
                    <x-text-input id="filter-search" name="search" type="text" class="mt-1 block w-full"
                                  :value="$filters['search'] ?? null" placeholder="{{ __('Name or booking id') }}" />
                </div>
                <div class="flex items-end gap-3">
                    <x-primary-button>{{ __('Filter') }}</x-primary-button>
                    <a href="{{ route('admin.disputes.index') }}" class="text-sm font-semibold text-slate-500 hover:text-primary">{{ __('Reset') }}</a>
                </div>
            </div>
        </form>

        <div class="{{ $cardBase }} p-0" :class="{{ $cardTheme }}">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-sm dark:divide-slate-800">
                    <thead>
                        <tr class="text-left text-xs font-semibold uppercase tracking-wider text-slate-400">
                            <th class="px-6 py-3">{{ __('Case') }}</th>
                            <th class="px-6 py-3">{{ __('Who') }}</th>
                            <th class="px-6 py-3">{{ __('Raised') }}</th>
                            <th class="px-6 py-3">{{ __('Status') }}</th>
                            <th class="px-6 py-3"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60">
                        @forelse ($disputes as $dispute)
                            <tr>
                                <td class="px-6 py-4">
                                    <p class="font-semibold text-slate-800 dark:text-slate-100">{{ $dispute->reasonLabel() }}</p>
                                    <p class="text-xs text-slate-500 dark:text-slate-400">
                                        #{{ str_pad((string) $dispute->id, 5, '0', STR_PAD_LEFT) }}
                                        @if ($dispute->booking)
                                            · {{ __('lesson') }} #{{ str_pad((string) $dispute->booking_id, 6, '0', STR_PAD_LEFT) }}
                                        @endif
                                    </p>
                                    @if ($dispute->details)
                                        <p class="mt-1 max-w-md truncate text-xs text-slate-400">{{ $dispute->details }}</p>
                                    @endif
                                </td>
                                <td class="px-6 py-4">
                                    <p class="text-slate-700 dark:text-slate-200">{{ $dispute->raisedBy?->name ?? __('Unknown') }}</p>
                                    @if ($dispute->against)
                                        <p class="text-xs text-slate-500 dark:text-slate-400">→ {{ $dispute->against->name }}</p>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-slate-500 dark:text-slate-400">{{ $dispute->created_at->format('d M Y') }}</td>
                                <td class="px-6 py-4">
                                    <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $dispute->status->badgeClasses() }}">{{ $dispute->status->label() }}</span>
                                    @if ($dispute->resolutionLabel())
                                        <p class="mt-1 text-xs text-slate-400">{{ $dispute->resolutionLabel() }}</p>
                                    @endif
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="{{ route('admin.disputes.show', $dispute) }}" class="text-sm font-semibold text-primary hover:underline">{{ __('Investigate') }}</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-6 py-10 text-center text-sm text-slate-500 dark:text-slate-400">{{ __('No disputes match those filters.') }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{ $disputes->links() }}
    </div>
</x-app-layout>
