@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
    $money = fn (int $minor) => platform_settings()->formatMinor($minor);
@endphp

<x-app-layout>
    <x-slot name="header">
        <div>
            <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Service purchases') }}</h2>
            <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ __('Every service purchase across the marketplace.') }}</p>
        </div>
    </x-slot>

    <div class="mx-auto max-w-6xl space-y-6">
        @if (session('status') === 'purchase-cancelled')
            <div class="rounded-2xl border border-slate-200/80 bg-slate-50 p-4 text-sm text-slate-700 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300">
                {{ __('Purchase cancelled.') }}
            </div>
        @elseif (session('status') === 'purchase-refunded')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                {{ __('Purchase refunded and cancelled.') }}
            </div>
        @endif

        <form method="GET" action="{{ route('admin.purchases.index') }}" class="flex flex-wrap items-center gap-2">
            <select name="status" onchange="this.form.submit()"
                    class="rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                <option value="">{{ __('All statuses') }}</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected($selectedStatus === $status->value)>{{ $status->label() }}</option>
                @endforeach
            </select>
            <select name="type" onchange="this.form.submit()"
                    class="rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200">
                <option value="">{{ __('All services') }}</option>
                @foreach ($types as $type)
                    <option value="{{ $type->value }}" @selected($selectedType === $type->value)>{{ $type->icon() }} {{ $type->label() }}</option>
                @endforeach
            </select>
        </form>

        <div class="{{ $cardBase }} overflow-x-auto" :class="{{ $cardTheme }}">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-slate-200 text-xs uppercase tracking-wider text-slate-400 dark:border-slate-800">
                        <th class="px-3 py-2">{{ __('Student') }}</th>
                        <th class="px-3 py-2">{{ __('Teacher') }}</th>
                        <th class="px-3 py-2">{{ __('Service') }}</th>
                        <th class="px-3 py-2">{{ __('Scope') }}</th>
                        <th class="px-3 py-2">{{ __('Price') }}</th>
                        <th class="px-3 py-2">{{ __('Status') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @forelse ($purchases as $purchase)
                        <tr>
                            <td class="px-3 py-3 font-semibold text-slate-700 dark:text-slate-200">
                                <a href="{{ route('admin.purchases.show', $purchase) }}" class="hover:text-primary">{{ $purchase->student->name }}</a>
                            </td>
                            <td class="px-3 py-3 text-slate-600 dark:text-slate-300">{{ $purchase->teacherProfile->user->name }}</td>
                            <td class="px-3 py-3 text-primary">{{ $purchase->service_type->icon() }} {{ $purchase->service_type->label() }}</td>
                            <td class="px-3 py-3 text-slate-600 dark:text-slate-300">{{ $purchase->scopeLabel() }}</td>
                            <td class="px-3 py-3 font-semibold text-slate-700 dark:text-slate-200">{{ $money($purchase->price_minor) }}</td>
                            <td class="px-3 py-3">
                                <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $purchase->status->badgeClasses() }}">{{ $purchase->status->label() }}</span>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-3 py-10 text-center text-slate-400">{{ __('No purchases yet.') }}</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div>{{ $purchases->links() }}</div>
    </div>
</x-app-layout>
