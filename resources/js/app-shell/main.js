import { createApp } from 'vue';
import AppShell from './AppShell.vue';

// Blade renders the route map, the signed-in user and the notification list into
// a `#studylikepro-shell-config` JSON island, and the page heading into a
// `#studylikepro-page-header` template. Both live inside the mount point, so they
// are read before Vue clears the container.
const DEFAULT_CONFIG = {
    appName: 'Studylikepro',
    csrfToken: '',
    user: { name: '', initials: '', roleLabel: 'Member', avatarUrl: null },
    navItems: [],
    notifications: { unreadCount: 0, items: [] },
    theme: { preset: 'classic', accent: 'indigo', mode: 'light' },
    urls: {},
};

function readConfig() {
    const island = document.getElementById('studylikepro-shell-config');

    if (!island || !island.textContent.trim()) {
        return DEFAULT_CONFIG;
    }

    try {
        const parsed = JSON.parse(island.textContent);

        return {
            ...DEFAULT_CONFIG,
            ...parsed,
            user: { ...DEFAULT_CONFIG.user, ...(parsed.user ?? {}) },
            notifications: { ...DEFAULT_CONFIG.notifications, ...(parsed.notifications ?? {}) },
            theme: { ...DEFAULT_CONFIG.theme, ...(parsed.theme ?? {}) },
            navItems: Array.isArray(parsed.navItems) ? parsed.navItems : [],
            urls: { ...DEFAULT_CONFIG.urls, ...(parsed.urls ?? {}) },
        };
    } catch (error) {
        console.error('Shell config could not be parsed; falling back to defaults.', error);

        return DEFAULT_CONFIG;
    }
}

function readHeaderHtml() {
    const template = document.getElementById('studylikepro-page-header');

    return template ? template.innerHTML.trim() : '';
}

const mountPoint = document.getElementById('studylikepro-shell');

if (mountPoint) {
    const config = readConfig();
    const headerHtml = readHeaderHtml();

    createApp(AppShell, { config, headerHtml }).mount(mountPoint);
}
