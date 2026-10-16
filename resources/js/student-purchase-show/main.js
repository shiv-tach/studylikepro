import { createApp } from 'vue';
import PurchaseShowApp from './PurchaseShowApp.vue';

/**
 * Blade renders the page's copy, prices, dates, status tones, actions and
 * pre-formatted sessions into a JSON island, so no URL, figure or translated
 * string is ever hardcoded here.
 */
const DEFAULTS = {
    appName: 'Studylikepro',
    csrfToken: '',
    theme: { preset: 'classic' },
    flash: null,
    error: null,
    status: null,
    plan: null,
    delivery: null,
    sessions: null,
    copy: {},
    urls: {},
};

function readConfig(id, defaults) {
    const island = document.getElementById(id);

    if (!island || !island.textContent.trim()) {
        return defaults;
    }

    try {
        const parsed = JSON.parse(island.textContent);

        return {
            ...defaults,
            ...parsed,
            theme: { ...defaults.theme, ...(parsed.theme ?? {}) },
            copy: { ...defaults.copy, ...(parsed.copy ?? {}) },
            urls: { ...defaults.urls, ...(parsed.urls ?? {}) },
        };
    } catch (error) {
        console.error(`${id} could not be parsed; falling back to defaults.`, error);

        return defaults;
    }
}

const mount = document.getElementById('student-purchase-show-app');

if (mount) {
    createApp(PurchaseShowApp, {
        config: readConfig('student-purchase-show-config', DEFAULTS),
    }).mount(mount);
}
