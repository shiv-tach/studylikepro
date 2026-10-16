import { createApp } from 'vue';
import AdminSettingsApp from './AdminSettingsApp.vue';

/**
 * Blade renders the grouped setting definitions (with their current, already
 * formatted values), the validation messages and the route map into a JSON
 * island, so no label, bound or URL is hardcoded here. Only /admin/settings
 * mounts this bundle.
 */
const DEFAULT_CONFIG = {
    appName: 'Studylikepro',
    csrfToken: '',
    theme: { preset: 'classic' },
    groups: [],
    errors: {},
    status: { saved: false },
    meta: { currencySymbol: '' },
    copy: {},
    urls: {},
};

function readConfig() {
    const island = document.getElementById('admin-settings-config');

    if (!island || !island.textContent.trim()) {
        return DEFAULT_CONFIG;
    }

    try {
        const parsed = JSON.parse(island.textContent);

        return {
            ...DEFAULT_CONFIG,
            ...parsed,
            theme: { ...DEFAULT_CONFIG.theme, ...(parsed.theme ?? {}) },
            groups: Array.isArray(parsed.groups) ? parsed.groups : [],
            errors: { ...DEFAULT_CONFIG.errors, ...(parsed.errors ?? {}) },
            status: { ...DEFAULT_CONFIG.status, ...(parsed.status ?? {}) },
            meta: { ...DEFAULT_CONFIG.meta, ...(parsed.meta ?? {}) },
            copy: { ...DEFAULT_CONFIG.copy, ...(parsed.copy ?? {}) },
            urls: { ...DEFAULT_CONFIG.urls, ...(parsed.urls ?? {}) },
        };
    } catch (error) {
        console.error('Platform settings config could not be parsed; falling back to defaults.', error);

        return DEFAULT_CONFIG;
    }
}

const mountPoint = document.getElementById('admin-settings-app');

if (mountPoint) {
    createApp(AdminSettingsApp, { config: readConfig() }).mount(mountPoint);
}
