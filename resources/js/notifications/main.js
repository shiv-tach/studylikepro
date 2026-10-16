import { createApp } from 'vue';
import NotificationsApp from './NotificationsApp.vue';

/**
 * Blade renders the email preference, the pre-formatted notification rows and
 * the route map into a JSON island, so no title, timestamp or URL is hardcoded
 * here. Only /notifications mounts this bundle.
 */
const DEFAULT_CONFIG = {
    appName: 'Studylikepro',
    csrfToken: '',
    theme: { preset: 'classic' },
    unreadCount: 0,
    emailEnabled: false,
    notifications: [],
    status: { read: false },
    copy: {},
    urls: {},
};

function readConfig() {
    const island = document.getElementById('notifications-config');

    if (!island || !island.textContent.trim()) {
        return DEFAULT_CONFIG;
    }

    try {
        const parsed = JSON.parse(island.textContent);

        return {
            ...DEFAULT_CONFIG,
            ...parsed,
            theme: { ...DEFAULT_CONFIG.theme, ...(parsed.theme ?? {}) },
            notifications: Array.isArray(parsed.notifications) ? parsed.notifications : [],
            status: { ...DEFAULT_CONFIG.status, ...(parsed.status ?? {}) },
            copy: { ...DEFAULT_CONFIG.copy, ...(parsed.copy ?? {}) },
            urls: { ...DEFAULT_CONFIG.urls, ...(parsed.urls ?? {}) },
        };
    } catch (error) {
        console.error('Notifications config could not be parsed; falling back to defaults.', error);

        return DEFAULT_CONFIG;
    }
}

const mountPoint = document.getElementById('notifications-app');

if (mountPoint) {
    createApp(NotificationsApp, { config: readConfig() }).mount(mountPoint);
}
