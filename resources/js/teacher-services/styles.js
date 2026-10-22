/**
 * The services catalog and its composer share one set of field, button and
 * surface recipes. Written out in full because Tailwind only sees complete
 * class strings.
 */
export const FIELD =
    'mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';

export const SMALL_FIELD =
    'rounded-lg border-slate-300 bg-white py-1.5 text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';

export const PRIMARY_BUTTON =
    'inline-flex items-center justify-center gap-1.5 rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 transition-colors hover:bg-primary/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 disabled:cursor-not-allowed disabled:opacity-50 disabled:shadow-none';

export const SECONDARY_BUTTON =
    'inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition-colors hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 disabled:cursor-not-allowed disabled:opacity-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 dark:hover:bg-slate-800';

export const GHOST_BUTTON =
    'inline-flex items-center justify-center gap-1.5 rounded-xl px-3 py-2 text-sm font-semibold text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100';

export const DANGER_GHOST_BUTTON =
    'inline-flex items-center justify-center gap-1.5 rounded-xl px-3 py-2 text-sm font-semibold text-slate-400 transition-colors hover:bg-rose-50 hover:text-rose-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-rose-300 disabled:cursor-not-allowed disabled:opacity-40 disabled:hover:bg-transparent disabled:hover:text-slate-400 dark:hover:bg-rose-950/30 dark:hover:text-rose-400';

export const TYPE_TILE =
    'flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary/10 p-2.5 text-primary';

export const SECTION_TITLE = 'text-sm font-bold text-slate-800 dark:text-slate-100';

export const SECTION_HINT = 'mt-1 text-xs text-slate-500 dark:text-slate-400';

/** The dashed "nothing here yet" ring the empty states use. */
export const EMPTY_RING = 'border-dashed border-slate-300 dark:border-slate-700';
