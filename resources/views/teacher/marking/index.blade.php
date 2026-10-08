@php
    $cardBase = 'rounded-2xl border p-6';
    $cardTheme = "themePreset === 'glass' || themePreset === 'ocean' ? 'border-slate-200/40 dark:border-slate-800/40 bg-white/70 dark:bg-slate-900/70 backdrop-blur-md' : 'border-slate-200/80 dark:border-slate-800/80 bg-white dark:bg-slate-900'";
@endphp

<x-app-layout>
    <x-slot name="header">
        <h2 class="font-bold text-xl text-slate-800 dark:text-slate-100 leading-tight">{{ __('Paper marking') }}</h2>
    </x-slot>

    <div class="mx-auto max-w-4xl space-y-6">
        @if (session('status') === 'marking-returned')
            <div class="rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300">
                {{ __('Marked copy delivered — the student has been notified.') }}
            </div>
        @endif

        <!-- Status filter tabs -->
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('teacher.marking.index') }}"
               class="rounded-full px-3 py-1.5 text-xs font-semibold transition-colors {{ $selectedStatus === null ? 'bg-primary text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-400' }}">
                {{ __('All') }}
            </a>
            @foreach ($statuses as $status)
                <a href="{{ route('teacher.marking.index', ['status' => $status->value]) }}"
                   class="rounded-full px-3 py-1.5 text-xs font-semibold transition-colors {{ $selectedStatus === $status->value ? 'bg-primary text-white' : 'bg-slate-100 text-slate-500 hover:bg-slate-200 dark:bg-slate-800 dark:text-slate-400' }}">
                    {{ $status->label() }}
                </a>
            @endforeach
        </div>

        @forelse ($orders as $order)
            <a href="{{ route('teacher.marking.show', $order) }}" class="block {{ $cardBase }} transition-shadow hover:shadow-md" :class="{{ $cardTheme }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-primary/10 px-2.5 py-0.5 text-[11px] font-semibold text-primary">
                                {{ $order->subject?->icon ?? '📘' }} {{ $order->subject?->name ?? __('Paper marking') }}
                            </span>
                            <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $order->status->badgeClasses() }}">{{ $order->status->label() }}</span>
                            @if ($order->isOverdue())
                                <span class="rounded-full bg-rose-100 px-2.5 py-0.5 text-[11px] font-semibold text-rose-700 dark:bg-rose-900/30 dark:text-rose-400">{{ __('Overdue') }}</span>
                            @endif
                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold text-slate-500 dark:bg-slate-800 dark:text-slate-400">{{ $order->submitted_at?->diffForHumans() }}</span>
                        </div>
                        <div class="mt-3 flex flex-wrap items-center gap-2 text-sm text-slate-600 dark:text-slate-300">
                            <span>👤 {{ $order->purchase?->student?->name }}</span>
                            @if ($order->page_count)
                                <span>·</span>
                                <span>{{ __(':pages pages', ['pages' => $order->page_count]) }}</span>
                            @endif
                            <span>·</span>
                            <span>{{ platform_settings()->formatMinor($order->purchase?->price_minor ?? 0) }}</span>
                        </div>
                    </div>
                    <div class="text-right text-xs text-slate-400">
                        @if ($deadline = $order->deadlineAt())
                            <p>{{ __('Due :time', ['time' => $deadline->format('d M, H:i')]) }}</p>
                        @endif
                    </div>
                </div>
            </a>
        @empty
            <div class="{{ $cardBase }} text-center" :class="{{ $cardTheme }}">
                <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('No paper-marking orders here yet.') }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ __('Once a student pays for your marking offer, their paper appears in this inbox.') }}</p>
            </div>
        @endforelse

        {{ $orders->links() }}
    </div>
</x-app-layout>
