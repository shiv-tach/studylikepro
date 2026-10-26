/**
 * The teaching profile is one form spread over several cards, so its field and
 * button classes live here instead of being retyped in each card. Written out
 * in full because Tailwind only sees complete class strings.
 */
export const FIELD =
    'mt-1 block w-full rounded-xl border-slate-300 bg-white text-base shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 sm:text-sm';

export const PRIMARY_BUTTON =
    'inline-flex items-center justify-center gap-1.5 rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 transition-colors hover:bg-primary/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 disabled:cursor-not-allowed disabled:opacity-60';

export const GHOST_BUTTON =
    'inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 transition-colors hover:border-slate-300 hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 dark:border-slate-700 dark:text-slate-300 dark:hover:border-slate-600';

/** The ring a language checkbox uses while it is unselected. */
export const OPTION_IDLE =
    'border-slate-200/80 hover:border-primary/40 dark:border-slate-800 dark:hover:border-primary/30';

export const OPTION_ACTIVE = 'border-primary bg-primary/5 ring-1 ring-primary/30';
