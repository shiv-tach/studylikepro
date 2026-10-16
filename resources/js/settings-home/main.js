import { createApp } from 'vue';
import SettingsHomeApp from './SettingsHomeApp.vue';

/**
 * Blade decides which settings cards exist, the unread badge, whether emails
 * are on and every route, then hands the lot over as a JSON island, so no card,
 * count or URL is hardcoded here. Only /settings mounts this bundle.
 */
const DEFAULT_CONFIG = {
    appName: 'Studylikepro',
    csrfToken: '',
    theme: { preset: 'classic' },
    user: {},
    notifications: { unreadCount: 0, emailEnabled: false },
    cards: [],
    tips: [],
    status: { notificationsUpdated: false },
    copy: {},
    urls: {},
};

function readConfig() {
    const island = document.getElementById('settings-config');

    if (!island || !island.textContent.trim()) {
        return DEFAULT_CONFIG;
    }

    try {
        const parsed = JSON.parse(island.textContent);

        return {
            ...DEFAULT_CONFIG,
            ...parsed,
            theme: { ...DEFAULT_CONFIG.theme, ...(parsed.theme ?? {}) },
            user: { ...DEFAULT_CONFIG.user, ...(parsed.user ?? {}) },
            notifications: { ...DEFAULT_CONFIG.notifications, ...(parsed.notifications ?? {}) },
            cards: Array.isArray(parsed.cards) ? parsed.cards : [],
            tips: Array.isArray(parsed.tips) ? parsed.tips : [],
            status: { ...DEFAULT_CONFIG.status, ...(parsed.status ?? {}) },
            copy: { ...DEFAULT_CONFIG.copy, ...(parsed.copy ?? {}) },
            urls: { ...DEFAULT_CONFIG.urls, ...(parsed.urls ?? {}) },
        };
    } catch (error) {
        console.error('Settings config could not be parsed; falling back to defaults.', error);

        return DEFAULT_CONFIG;
    }
}

const mountPoint = document.getElementById('settings-app');

if (mountPoint) {
    createApp(SettingsHomeApp, { config: readConfig() }).mount(mountPoint);
}
