@props(['groups' => []])

{{-- The list itself, shared by the desktop aside and the phone disclosure. --}}
<ol class="space-y-3.5">
    @foreach ($groups as $group)
        <li>
            <p class="flex items-center gap-1.5 text-[10px] font-bold uppercase tracking-wider text-slate-400">
                <span aria-hidden="true">{{ $group['icon'] }}</span>
                {{ $group['label'] }}
            </p>

            <ul class="mt-1 space-y-1.5">
                @foreach ($group['items'] as $item)
                    <li class="flex items-start gap-2">
                        <span @class([
                            'mt-0.5 flex h-4 w-4 shrink-0 items-center justify-center rounded-full',
                            'bg-emerald-500 text-white' => $item['done'],
                            'border border-dashed border-slate-300 text-slate-300 dark:border-slate-700 dark:text-slate-600' => ! $item['done'],
                        ]) aria-hidden="true">
                            @if ($item['done'])
                                <svg class="h-2.5 w-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3.5" d="M5 13l4 4L19 7" />
                                </svg>
                            @endif
                        </span>

                        <span class="min-w-0 flex-1">
                            <span class="block text-[10px] font-semibold uppercase tracking-wide text-slate-400">{{ $item['label'] }}</span>

                            @if ($item['url'])
                                <a href="{{ $item['url'] }}"
                                   class="block break-words text-xs font-semibold text-slate-700 underline-offset-2 transition-colors hover:text-primary hover:underline dark:text-slate-200">
                                    {{ $item['value'] ?? __('Not answered yet') }}
                                </a>
                            @else
                                <span @class([
                                    'block break-words text-xs font-semibold',
                                    'text-slate-700 dark:text-slate-200' => $item['value'] !== null,
                                    'text-slate-400 dark:text-slate-500' => $item['value'] === null,
                                ])>
                                    {{ $item['value'] ?? __('Not answered yet') }}
                                </span>
                            @endif

                            @if ($item['meta'])
                                <span class="mt-0.5 block break-words text-[11px] text-slate-400 dark:text-slate-500">{{ $item['meta'] }}</span>
                            @endif
                        </span>
                    </li>
                @endforeach
            </ul>
        </li>
    @endforeach
</ol>
