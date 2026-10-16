import { createApp } from 'vue';
import PurchasesApp from './PurchasesApp.vue';

/**
 * Blade renders the page's copy, tabs, service-type chips, figures and
 * pre-formatted purchase cards into a JSON island, so no URL, price or
 * translated string is ever hardcoded here.
 */
const DEFAULTS = {
    appName: 'Studylikepro',
    csrfToken: '',
    theme: { preset: 'classic' },
    flash: { cancelled: false },
    tab: 'active',
    tabs: [],
    typeOptions: [],
    selectedType: '',
    stats: [],
    purchases: [],
    filtered: false,
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

const mount = document.getElementById('student-purchases-app');

if (mount) {
    createApp(PurchasesApp, {
        config: readConfig('student-purchases-config', DEFAULTS),
    }).mount(mount);
}
