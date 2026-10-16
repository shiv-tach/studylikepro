/**
 * The theme page previews each preset with a miniature layout. Tailwind only
 * compiles classes it can literally see, so every surface is spelled out in
 * full here (never assembled from a variable) and keyed by the preset id Blade
 * sends in the island.
 */
export const PRESET_VISUALS = {
    classic: {
        sidebar: 'bg-white',
        body: 'bg-slate-50',
        accentBar: 'from-indigo-500 to-blue-500',
        dot: 'bg-indigo-500',
    },
    forest: {
        sidebar: 'bg-white',
        body: 'bg-emerald-50/40',
        accentBar: 'from-emerald-500 to-teal-500',
        dot: 'bg-emerald-500',
    },
    midnight: {
        sidebar: 'bg-slate-950',
        body: 'bg-slate-900',
        accentBar: 'from-slate-600 to-indigo-600',
        dot: 'bg-slate-600',
    },
    sunset: {
        sidebar: 'bg-white/90',
        body: 'bg-amber-50/40',
        accentBar: 'from-amber-500 via-rose-500 to-indigo-500',
        dot: 'bg-amber-500',
    },
    glass: {
        sidebar: 'bg-white/70 backdrop-blur-lg',
        body: 'bg-slate-100',
        accentBar: 'from-violet-500 to-fuchsia-500',
        dot: 'bg-violet-500',
    },
    ocean: {
        sidebar: 'bg-white/80 backdrop-blur-md',
        body: 'bg-cyan-50/30',
        accentBar: 'from-cyan-400 via-blue-500 to-indigo-500',
        dot: 'bg-cyan-400',
    },
};

/** The accent swatches the preview dots and the picker rings use. */
export const ACCENT_VISUALS = {
    indigo: { swatch: 'bg-indigo-500', ring: 'ring-indigo-500' },
    violet: { swatch: 'bg-violet-500', ring: 'ring-violet-500' },
    emerald: { swatch: 'bg-emerald-500', ring: 'ring-emerald-500' },
    rose: { swatch: 'bg-rose-500', ring: 'ring-rose-500' },
    amber: { swatch: 'bg-amber-500', ring: 'ring-amber-500' },
    blue: { swatch: 'bg-blue-500', ring: 'ring-blue-500' },
    cyan: { swatch: 'bg-cyan-500', ring: 'ring-cyan-500' },
};

export function presetVisuals(id) {
    return PRESET_VISUALS[id] ?? PRESET_VISUALS.classic;
}

export function accentVisuals(id) {
    return ACCENT_VISUALS[id] ?? ACCENT_VISUALS.indigo;
}
