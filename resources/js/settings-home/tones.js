/**
 * Icon-tile colour per settings card, spelled out in full because Tailwind only
 * compiles classes it can literally see. The tone is semantic (theme, profile,
 * notifications…) and never the runtime accent, so a preset never recolours it.
 */
export const CARD_TONES = {
    violet: 'bg-violet-100 text-violet-600 dark:bg-violet-900/30 dark:text-violet-400',
    emerald: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400',
    amber: 'bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400',
    rose: 'bg-rose-100 text-rose-600 dark:bg-rose-900/30 dark:text-rose-400',
    cyan: 'bg-cyan-100 text-cyan-600 dark:bg-cyan-900/30 dark:text-cyan-400',
    blue: 'bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400',
    slate: 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
};

export function cardTone(tone) {
    return CARD_TONES[tone] ?? CARD_TONES.slate;
}

export default cardTone;
