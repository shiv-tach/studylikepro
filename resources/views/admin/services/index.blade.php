@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Service catalog') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Every teacher-defined offering across the marketplace.') }}</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6">
        @if (session('status') === 'offering-deleted')
            <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4 text-sm text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">
                {{ __('Offering deleted.') }}
            </div>
        @elseif (session('status') === 'offering-has-purchases')
            <div class="rounded-2xl border border-amber-200/80 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300">
                {{ __('This offering has purchases and cannot be deleted — deactivate it instead.') }}
            </div>
        @endif

        <form method="GET" action="{{ route('admin.services.index') }}" class="flex flex-wrap items-center gap-2">
            <select name="type" onchange="this.form.submit()"
                    class="rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                <option value="">{{ __('All service types') }}</option>
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected($selectedType === $type->value)>{{ $type->icon() }} {{ $type->label() }}</option>
                @endforeach
            </select>
        </form>

        <div class="{{ $cardBase }} overflow-x-auto" :class="{{ $cardTheme }}">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-xs uppercase tracking-wider text-slate-400 dark:border-slate-800">
                        <th class="px-3 py-2">{{ __('Teacher') }}</th>
                        <th class="px-3 py-2">{{ __('Service') }}</th>
                        <th class="px-3 py-2">{{ __('Scope') }}</th>
                        <th class="px-3 py-2">{{ __('Price') }}</th>
                        <th class="px-3 py-2">{{ __('Details') }}</th>
                        <th class="px-3 py-2">{{ __('Status') }}</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($offerings as $offering)
                        <tr>
                            <td class="px-3 py-3 font-semibold text-slate-700 dark:text-slate-200">{{ $offering->teacherProfile->user->name }}</td>
                            <td class="px-3 py-3 text-primary">{{ $offering->service_type->icon() }} {{ $offering->service_type->label() }}</td>
                            <td class="px-3 py-3 text-slate-600 dark:text-slate-300">
                                {{ $offering->lesson?->name ?? $offering->subject?->name ?? '—' }}
                                @if ($offering->subject && $offering->lesson) <span class="text-xs text-slate-400">({{ $offering->subject->name }})</span> @endif
                            </td>
                            <td class="px-3 py-3 font-semibold text-slate-700 dark:text-slate-200">{{ $money($offering->price_minor) }}</td>
                            <td class="px-3 py-3 text-xs text-slate-500 dark:text-slate-400">
                                @if ($offering->allocation_minutes) {{ __(':m min allocation', ['m' => $offering->allocation_minutes]) }} @endif
                                @if ($offering->max_sessions) · {{ __('max :n sessions', ['n' => $offering->max_sessions]) }} @endif
                                @if ($offering->turnaround_hours) · {{ __(':h h turnaround', ['h' => $offering->turnaround_hours]) }} @endif
                                @if ($offering->max_pages) · {{ __('max :p pages', ['p' => $offering->max_pages]) }} @endif
                            </td>
                            <td class="px-3 py-3">
                                <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $offering->is_active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400' }}">
                                    {{ $offering->is_active ? __('Active') : __('Paused') }}
                                </span>
                            </td>
                            <td class="px-3 py-3 text-right">
                                <form method="POST" action="{{ route('admin.services.destroy', $offering) }}"
                                      onsubmit="return confirm('{{ __('Delete this offering?') }}')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs font-semibold text-rose-500 hover:text-rose-600">{{ __('Delete') }}</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-3 py-10 text-center text-slate-400">{{ __('No offerings yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $offerings->links() }}</div>
    </div>
</x-app-layout>
