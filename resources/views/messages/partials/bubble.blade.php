@props(['message', 'mine'])

@php
    $align = $mine ? 'justify-end' : 'justify-start';
@endphp
@if ($message->isSystem())
    <p class="mx-auto max-w-md rounded-full bg-slate-100 px-4 py-1.5 text-center text-xs text-slate-500 dark:bg-slate-800/70 dark:text-slate-400">
        {{ $message->body }}
    </p>
@else
    <div class="flex {{ $align }} gap-3" data-message-id="{{ $message->id }}">
        @unless ($mine)
            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-200 text-xs font-bold text-slate-600 dark:bg-slate-700 dark:text-slate-200">
                {{ mb_substr($message->sender?->name ?? '?', 0, 2) }}
            </div>
        @endunless

        <div class="max-w-[80%]">
            <p class="text-xs text-slate-400 {{ $mine ? 'text-right' : '' }}">
                {{ $mine ? __('You') : ($message->sender?->name ?? __('Someone')) }} · {{ $message->created_at->format('H:i') }}
            </p>

            @if ($message->body)
                <div class="mt-1 whitespace-pre-line rounded-2xl px-4 py-2 text-sm {{ $mine ? 'rounded-br-md bg-primary text-white' : 'rounded-bl-md bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-200' }}">
                    {{ $message->body }}
                </div>
            @endif

            @if ($message->attachment_path)
                <a href="{{ $message->attachmentUrl() }}" target="_blank" rel="noopener noreferrer" class="mt-2 block">
                    <img src="{{ $message->attachmentUrl() }}" alt="{{ __('Shared photo') }}"
                         class="max-h-64 rounded-xl border border-slate-200 object-cover dark:border-slate-700">
                </a>
            @endif
        </div>
    </div>
@endif
