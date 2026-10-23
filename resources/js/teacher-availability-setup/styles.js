/**
 * Field and button classes for the availability step, written out in full
 * because Tailwind only compiles complete class strings.
 */
export const FIELD =
    'rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';

export const PRIMARY_BUTTON =
    'inline-flex items-center justify-center gap-1.5 rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 transition-colors hover:bg-primary/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 disabled:cursor-not-allowed disabled:opacity-60';

export const GHOST_BUTTON =
    'inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-200 px-4 py-2.5 text-sm font-semibold text-slate-600 transition-colors hover:border-slate-300 hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 dark:border-slate-700 dark:text-slate-300 dark:hover:border-slate-600';

export const PRESET_CARD =
    'flex h-full flex-col gap-2 rounded-2xl border p-4 text-left transition-all duration-300 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40';

export const OPTION_IDLE =
    'border-slate-200/80 hover:border-primary/40 dark:border-slate-800 dark:hover:border-primary/30';

export const OPTION_ACTIVE = 'border-primary bg-primary/5 ring-1 ring-primary/30';
