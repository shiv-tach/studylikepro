/**
 * Shared surface and chip classes for the workspace Vue apps, written out in
 * full so Tailwind's scanner picks every class up. `panelClass` switches
 * between an opaque card and the frosted glass the translucent presets use.
 */
const SURFACE = 'rounded-2xl border p-6 transition-all duration-300';

export const PANEL = `${SURFACE} border-slate-200/80 bg-white dark:border-slate-800/80 dark:bg-slate-900`;

export const TRANSLUCENT_PANEL = `${SURFACE} border-slate-200/40 bg-white/70 backdrop-blur-md dark:border-slate-800/40 dark:bg-slate-900/70`;

export const PANEL_HOVER = 'hover:border-primary/40 hover:shadow-md dark:hover:border-primary/30';

/** A preset-independent card, for grids that carry their own layout. */
export const CARD = 'rounded-2xl border border-slate-200/80 bg-white transition-all duration-300 dark:border-slate-800/80 dark:bg-slate-900';

export const CARD_HOVER = 'hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-lg hover:shadow-primary/5 dark:hover:border-primary/40';

export const CHIP = 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-semibold';

/** Chip colours are semantic (never the theme accent) and dark-mode safe. */
export const CHIP_TONES = {
    slate: 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
    primary: 'bg-primary/10 text-primary',
    amber: 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
    emerald: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300',
    sky: 'bg-sky-100 text-sky-700 dark:bg-sky-950/40 dark:text-sky-300',
    rose: 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300',
};

export const MUTED_TEXT = 'text-slate-500 dark:text-slate-400';

export const HEADING_TEXT = 'text-slate-800 dark:text-slate-100';

export function panelClass(preset, interactive = false) {
    const surface = preset === 'glass' || preset === 'ocean' ? TRANSLUCENT_PANEL : PANEL;

    return interactive ? `${surface} ${PANEL_HOVER}` : surface;
}

export default panelClass;
