/**
 * Tailwind can only compile classes it sees literally, so the survey's shared
 * recipes live here instead of being rebuilt from strings in every component.
 */
export const CARD = 'survey-step rounded-2xl border border-slate-200/80 bg-white p-5 shadow-sm dark:border-slate-800/80 dark:bg-slate-900 sm:p-6';

export const FIELD = 'mt-1 block w-full rounded-xl border-slate-300 bg-white text-base shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200 sm:text-sm';

export const NUMBER_FIELD = 'block w-full rounded-xl border-slate-300 bg-white text-lg font-bold text-slate-900 shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100 sm:text-base';

export const LABEL = 'block text-sm font-semibold text-slate-700 dark:text-slate-300';

export const MUTED = 'text-xs text-slate-500 dark:text-slate-400';

export const PRIMARY_BUTTON = 'inline-flex min-h-11 items-center justify-center gap-2 rounded-xl bg-primary px-6 py-2.5 text-sm font-bold text-white shadow-md shadow-primary/20 transition-colors hover:bg-primary/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 disabled:cursor-not-allowed disabled:opacity-60';

export const GHOST_BUTTON = 'inline-flex min-h-11 items-center rounded-xl px-3 text-sm font-semibold text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-800 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-100';
