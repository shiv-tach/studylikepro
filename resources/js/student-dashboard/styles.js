/**
 * Card surfaces for the dashboard, written out in full so Tailwind's scanner
 * picks every class up. The preset decides between an opaque card and the
 * frosted glass the translucent presets use.
 */
const SURFACE = 'rounded-2xl border p-6 transition-all duration-300';

export const PANEL = `${SURFACE} border-slate-200/80 bg-white dark:border-slate-800/80 dark:bg-slate-900`;

export const TRANSLUCENT_PANEL = `${SURFACE} border-slate-200/40 bg-white/70 backdrop-blur-md dark:border-slate-800/40 dark:bg-slate-900/70`;

export const PANEL_HOVER = 'hover:border-primary/40 hover:shadow-md dark:hover:border-primary/30';

export const CHIP = 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-[11px] font-semibold';

export function panelClass(preset, interactive = false) {
    const surface = preset === 'glass' || preset === 'ocean' ? TRANSLUCENT_PANEL : PANEL;

    return interactive ? `${surface} ${PANEL_HOVER}` : surface;
}

export default panelClass;
