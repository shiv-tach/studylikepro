import { createApp } from 'vue';
import SettingsThemeApp from './SettingsThemeApp.vue';

/**
 * Blade renders the preset list, the accent list, the mode and sidebar options,
 * the user's saved choice and the route map into a JSON island, so no label,
 * preset name or URL is hardcoded here. Only /settings/theme mounts this bundle.
 */
const DEFAULT_CONFIG = {
    appName: 'Studylikepro',
    csrfToken: '',
    theme: { preset: 'classic' },
    selected: { preset: 'classic', accent: 'indigo', mode: 'light', sidebarStyle: 'flat' },
    presets: [],
    accents: [],
    modes: [],
    sidebarStyles: [],
    status: { updated: false },
    copy: {},
    urls: {},
};

function readConfig() {
    const island = document.getElementById('settings-theme-config');

    if (!island || !island.textContent.trim()) {
        return DEFAULT_CONFIG;
    }

    try {
        const parsed = JSON.parse(island.textContent);

        return {
            ...DEFAULT_CONFIG,
            ...parsed,
            theme: { ...DEFAULT_CONFIG.theme, ...(parsed.theme ?? {}) },
            selected: { ...DEFAULT_CONFIG.selected, ...(parsed.selected ?? {}) },
            presets: Array.isArray(parsed.presets) ? parsed.presets : [],
            accents: Array.isArray(parsed.accents) ? parsed.accents : [],
            modes: Array.isArray(parsed.modes) ? parsed.modes : [],
            sidebarStyles: Array.isArray(parsed.sidebarStyles) ? parsed.sidebarStyles : [],
            status: { ...DEFAULT_CONFIG.status, ...(parsed.status ?? {}) },
            copy: { ...DEFAULT_CONFIG.copy, ...(parsed.copy ?? {}) },
            urls: { ...DEFAULT_CONFIG.urls, ...(parsed.urls ?? {}) },
        };
    } catch (error) {
        console.error('Theme settings config could not be parsed; falling back to defaults.', error);

        return DEFAULT_CONFIG;
    }
}

const mountPoint = document.getElementById('settings-theme-app');

if (mountPoint) {
    createApp(SettingsThemeApp, { config: readConfig() }).mount(mountPoint);
}
