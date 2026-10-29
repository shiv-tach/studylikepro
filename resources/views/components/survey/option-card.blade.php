@props([
    'type' => 'radio',
    'name',
    'value' => null,
    'checked' => false,
    'icon' => null,
    'title' => null,
    'hint' => null,
    'meta' => null,
    'badge' => null,
    'align' => 'start',
    'required' => false,
])

{{--
    One selectable answer of the invite survey.

    The real input stays a `peer sr-only`, so the card is the control and the
    no-JS form still submits the same name/value pair the controller validates.
    Attributes passed at the call site (`x-model`, `x-on:change`, …) land on the
    input, which is where Alpine expects them.
--}}
<label class="relative block h-full">
    <input type="{{ $type }}" name="{{ $name }}"
           @if ($value !== null) value="{{ $value }}" @endif
           class="peer sr-only"
           @checked($checked)
           @required($required)
           {{ $attributes }}>

    <span @class([
        'flex h-full cursor-pointer flex-col justify-center gap-2 rounded-2xl border-2 border-slate-200 bg-white p-4 transition duration-200',
        'hover:-translate-y-0.5 hover:border-slate-300 hover:shadow-sm motion-reduce:transform-none motion-reduce:transition-none',
        'peer-checked:border-primary peer-checked:bg-primary/5 peer-checked:shadow-md peer-checked:shadow-primary/10',
        'peer-focus-visible:ring-2 peer-focus-visible:ring-primary/40 peer-focus-visible:ring-offset-2',
        'dark:border-slate-800 dark:bg-slate-900 dark:hover:border-slate-700 dark:peer-checked:border-primary/70 dark:peer-focus-visible:ring-offset-slate-950',
        'items-center text-center' => $align === 'center',
    ])>
        <span @class([
            'flex w-full gap-3',
            'flex-col items-center text-center' => $align === 'center',
            'items-start' => $align !== 'center',
        ])>
            @if ($icon)
                <span class="text-2xl leading-none" aria-hidden="true">{{ $icon }}</span>
            @endif

            <span class="min-w-0 flex-1">
                <span class="flex flex-wrap items-center gap-2">
                    <span class="text-base font-bold text-slate-900 dark:text-slate-100">{{ $title }}</span>

                    @if ($badge)
                        <span class="inline-flex items-center rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-primary">
                            {{ $badge }}
                        </span>
                    @endif
                </span>

                @if ($hint)
                    <span class="mt-1 block text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ $hint }}</span>
                @endif

                @if ($meta)
                    <span class="mt-1.5 block text-xs font-semibold leading-relaxed text-slate-700 dark:text-slate-300">{{ $meta }}</span>
                @endif

                {{ $slot }}
            </span>
        </span>
    </span>

    {{-- Sibling of the input, so `peer-checked` can reveal it. --}}
    <span class="pointer-events-none absolute right-3 top-3 flex h-5 w-5 items-center justify-center rounded-full bg-primary text-white opacity-0 shadow-sm transition-opacity duration-200 peer-checked:opacity-100 motion-reduce:transition-none"
          aria-hidden="true">
        <svg class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
        </svg>
    </span>
</label>
