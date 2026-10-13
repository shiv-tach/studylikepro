/**
 * Theme-preset class maps for the shell chrome.
 *
 * Tailwind only compiles classes it can literally see, so every surface is
 * spelled out in full here rather than assembled from a variable. The preset
 * itself is owned by the head bootstrap script + the Alpine theme listener in
 * `layouts/app.blade.php`; these maps let the Vue shell follow along.
 */

export const PRESETS = ['classic', 'forest', 'midnight', 'sunset', 'glass', 'ocean'];

const SIDEBAR = {
    classic: 'bg-white border-slate-200/80 dark:border-slate-800/80 dark:bg-slate-900',
    forest: 'bg-white border-slate-200/80 dark:border-slate-800/80 dark:bg-slate-900',
    midnight: 'bg-slate-950 border-slate-900 dark:border-slate-900/60 text-slate-100',
    sunset: 'bg-white/90 border-slate-200/50 dark:bg-slate-900/85 dark:border-slate-800/50',
    glass: 'bg-white/70 border-slate-200/40 backdrop-blur-lg dark:bg-slate-900/70 dark:border-slate-800/40',
    ocean: 'bg-white/80 border-cyan-200/60 backdrop-blur-md dark:bg-slate-900/80 dark:border-cyan-800/40',
};

const SIDEBAR_DIVIDER = {
    midnight: 'border-slate-800/80',
    ocean: 'border-cyan-200/50 dark:border-cyan-800/30',
};

const HEADER = {
    classic: 'bg-white/80 dark:bg-slate-900/80',
    forest: 'bg-white/80 dark:bg-slate-900/80',
    midnight: 'bg-slate-950/80 dark:bg-slate-950/80',
    sunset: 'bg-white/90 dark:bg-slate-900/85',
    glass: 'bg-white/70 border-slate-200/40 backdrop-blur-lg dark:bg-slate-900/70 dark:border-slate-800/40',
    ocean: 'bg-white/80 border-cyan-200/60 backdrop-blur-md dark:bg-slate-900/80 dark:border-cyan-800/40',
};

export function sidebarClass(preset) {
    return SIDEBAR[preset] ?? SIDEBAR.classic;
}

export function sidebarDividerClass(preset) {
    return ['border-slate-200/50 dark:border-slate-800/50', SIDEBAR_DIVIDER[preset]].filter(Boolean).join(' ');
}

export function headerClass(preset) {
    return ['border-slate-200/80 dark:border-slate-800/80', HEADER[preset] ?? HEADER.classic]
        .filter(Boolean)
        .join(' ');
}

export function sidebarFooterSurfaceClass(preset) {
    return preset === 'midnight'
        ? 'bg-slate-900/60'
        : 'bg-slate-50 dark:bg-slate-800/30';
}

export function navItemClass(preset, active) {
    if (active) {
        return 'text-primary bg-primary/10 dark:bg-primary/20 shadow-sm shadow-primary/5';
    }

    return preset === 'midnight'
        ? 'text-slate-300 hover:bg-slate-800 hover:text-white'
        : 'text-slate-600 hover:bg-slate-50 dark:text-slate-400 dark:hover:bg-slate-800/50';
}
