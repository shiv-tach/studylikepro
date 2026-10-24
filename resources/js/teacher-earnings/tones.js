/**
 * The literal Tailwind classes each semantic money tone uses. Blade ships a
 * tone name (never a class), because Tailwind can only compile classes it can
 * see, and the enums live outside its scanned paths.
 */
export const TILE_TONES = {
    emerald: {
        icon: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300',
        value: 'text-emerald-600 dark:text-emerald-400',
    },
    amber: {
        icon: 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
        value: 'text-amber-600 dark:text-amber-400',
    },
    primary: {
        icon: 'bg-primary/10 text-primary',
        value: 'text-primary',
    },
    slate: {
        icon: 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
        value: 'text-slate-800 dark:text-slate-100',
    },
};

export function tileTone(tone) {
    return TILE_TONES[tone] ?? TILE_TONES.slate;
}
