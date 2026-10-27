/**
 * The verification page is one long form split over several cards, so its field
 * and button classes live here instead of being retyped in each card. Written
 * out in full because Tailwind only sees complete class strings.
 */
export const FIELD =
    'mt-1 block w-full rounded-xl border-slate-300 bg-white text-base shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 sm:text-sm';

export const PRIMARY_BUTTON =
    'inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 transition-colors hover:bg-primary/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none';

export const SECONDARY_BUTTON =
    'inline-flex min-h-11 items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800';

export const GHOST_BUTTON =
    'inline-flex min-h-10 items-center justify-center gap-1.5 rounded-xl px-3 py-2 text-sm font-semibold text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100';

export const DANGER_GHOST_BUTTON =
    'inline-flex min-h-11 min-w-11 items-center justify-center gap-1.5 rounded-xl px-3 py-2 text-sm font-semibold text-slate-400 transition-colors hover:bg-rose-50 hover:text-rose-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-300 dark:hover:bg-rose-950/30 dark:hover:text-rose-400';

/** The dashed "nothing here yet" ring the checklist uses. */
export const EMPTY_RING = 'border-dashed border-slate-300 dark:border-slate-700';

export const DONE_RING = 'border-emerald-200 bg-emerald-100 dark:border-emerald-900/50 dark:bg-emerald-950/40';

/**
 * The upload slot's drop target. Layout only — the border colour is picked by
 * the component so a hover/dragring never fights a second literal class.
 */
export const UPLOAD_ZONE =
    'flex w-full flex-col items-center justify-center gap-1 rounded-xl border-2 border-dashed px-3 py-4 text-center transition-colors duration-200 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 disabled:cursor-not-allowed disabled:opacity-60 motion-reduce:transition-none';

/** The live upload bar: one track, one filled bar, both literal for Tailwind. */
export const PROGRESS_TRACK =
    'mt-2 h-1.5 w-full overflow-hidden rounded-full bg-slate-200/80 dark:bg-slate-800';

export const PROGRESS_BAR =
    'h-full rounded-full bg-primary transition-all duration-200 ease-out motion-reduce:transition-none';
