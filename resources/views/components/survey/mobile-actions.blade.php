@props(['form', 'backUrl' => null, 'backLabel' => null, 'submitLabel' => null])

{{--
    Thumb-reachable Back/Continue bar for phones. It is a real submit button
    wired to the form by id, so the step keeps working with JavaScript off and
    the inline row inside the card can stay desktop-only.
--}}
<div class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200/80 bg-white/95 px-4 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3 backdrop-blur-md dark:border-slate-800/80 dark:bg-slate-950/95 lg:hidden">
    <div class="mx-auto flex w-full max-w-3xl items-center gap-3">
        @if ($backUrl)
            <a href="{{ $backUrl }}"
               class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl border border-slate-200 px-4 text-sm font-semibold text-slate-600 transition-colors hover:border-slate-300 hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 dark:border-slate-700 dark:text-slate-300 dark:hover:border-slate-600">
                {{ $backLabel ?? __('Back') }}
            </a>
        @endif

        <button type="submit" form="{{ $form }}"
                class="inline-flex min-h-11 flex-1 items-center justify-center gap-1.5 rounded-xl bg-primary px-5 text-sm font-bold text-white shadow-md shadow-primary/20 transition-colors hover:bg-primary/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 disabled:cursor-not-allowed disabled:opacity-60">
            {{ $submitLabel ?? __('Continue') }}
            <span aria-hidden="true">→</span>
        </button>
    </div>
</div>
