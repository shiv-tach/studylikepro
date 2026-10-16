import { createApp } from 'vue';
import App from './App.vue';

/**
 * Blade renders the unread summary, the pre-formatted threads and the route map
 * into a JSON island, so no URL, count or translated string is hardcoded here.
 * Only the `/messages` inbox mounts this bundle.
 */
const DEFAULT_CONFIG = {
    appName: 'Studylikepro',
    theme: { preset: 'classic' },
    totalUnread: 0,
    threads: [],
    copy: {},
    urls: {},
};

function readConfig() {
    const island = document.getElementById('messages-config');

    if (!island || !island.textContent.trim()) {
        return DEFAULT_CONFIG;
    }

    try {
        const parsed = JSON.parse(island.textContent);

        return {
            ...DEFAULT_CONFIG,
            ...parsed,
            theme: { ...DEFAULT_CONFIG.theme, ...(parsed.theme ?? {}) },
            threads: Array.isArray(parsed.threads) ? parsed.threads : [],
            copy: { ...DEFAULT_CONFIG.copy, ...(parsed.copy ?? {}) },
            urls: { ...DEFAULT_CONFIG.urls, ...(parsed.urls ?? {}) },
        };
    } catch (error) {
        console.error('Messages config could not be parsed; falling back to defaults.', error);

        return DEFAULT_CONFIG;
    }
}

const mountPoint = document.getElementById('messages-app');

if (mountPoint) {
    createApp(App, { config: readConfig() }).mount(mountPoint);
}
