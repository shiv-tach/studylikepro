@props(['booking', 'timezone'])

<ol class="space-y-5 border-l border-slate-200 pl-6 dark:border-slate-800">
    @foreach ($booking->timeline() as $step)
        <li class="relative">
            <span class="absolute -left-[31px] top-1 flex h-3.5 w-3.5 items-center justify-center rounded-full border-2 border-white dark:border-slate-900 {{ $step['at'] ? 'bg-primary' : 'bg-slate-300 dark:bg-slate-700' }}"></span>
            <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                {{ $step['label'] }}
                @if ($step['at'])
                    <span class="font-normal text-slate-400">· {{ $step['at']->copy()->setTimezone($timezone)->format('d M Y, H:i') }}</span>
                @else
                    <span class="font-normal text-slate-400">· {{ __('pending') }}</span>
                @endif
            </p>
            <p class="mt-0.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ $step['description'] }}</p>

            @if ($step['key'] === 'cancelled' && $booking->cancellation_reason)
                <p class="mt-1 text-xs italic text-slate-500 dark:text-slate-400">“{{ $booking->cancellation_reason }}”</p>
            @endif
        </li>
    @endforeach
</ol>
