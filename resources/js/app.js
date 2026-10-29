import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

// Counts down the SMS resend cooldown on the registration verify screen. The
// server also enforces it; this only keeps the button honest.
Alpine.data('otpResend', (seconds = 0) => ({
    seconds,

    init() {
        this.tick();
    },

    tick() {
        if (this.seconds <= 0) {
            return;
        }

        setTimeout(() => {
            this.seconds -= 1;
            this.tick();
        }, 1000);
    },
}));

// Light/dark switch for the guest survey header (and any other public screen
// that opts in). It writes the same `themeMode` key the blocking head script
// and the Theme Settings screen use, so the choice survives the next step. The
// document owns the background, so the surface class has to be swapped here.
const SURFACES = {
    classic: 'bg-slate-50',
    forest: 'bg-slate-50',
    midnight: 'bg-slate-900',
    sunset: 'bg-amber-50/40',
    glass: 'bg-slate-100',
    ocean: 'bg-cyan-50/30',
};

Alpine.data('themeToggle', () => ({
    dark: false,
    lightSurface: 'bg-slate-50',

    init() {
        this.dark = document.documentElement.classList.contains('dark');

        const preset = localStorage.getItem('themePreset')
            || document.documentElement.dataset.preset
            || 'classic';

        this.lightSurface = SURFACES[preset] ?? SURFACES.classic;
    },

    toggle() {
        this.dark = ! this.dark;

        const root = document.documentElement;

        Object.values(SURFACES).forEach((name) => root.classList.remove(name));
        root.classList.remove('bg-slate-950');

        root.classList.toggle('dark', this.dark);
        root.classList.add(this.dark ? 'bg-slate-950' : this.lightSurface);

        localStorage.setItem('themeMode', this.dark ? 'dark' : 'light');

        window.dispatchEvent(new CustomEvent('theme-changed', {
            detail: { mode: this.dark ? 'dark' : 'light' },
        }));
    },
}));

// One question per screen means one submit per screen: the first click locks
// the form so a double tap cannot post the same answer twice while the page
// transition is still running.
Alpine.data('submitOnce', () => ({
    busy: false,

    init() {
        // Restored from the back/forward cache: the page was never submitted.
        window.addEventListener('pageshow', () => {
            this.busy = false;
        });
    },

    guard(event) {
        if (this.busy) {
            event.preventDefault();

            return;
        }

        this.busy = true;
    },
}));

Alpine.start();
