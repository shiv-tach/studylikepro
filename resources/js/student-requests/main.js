import { createApp } from 'vue';
import CreateRequestApp from './CreateRequestApp.vue';
import RequestsApp from './RequestsApp.vue';

/**
 * Blade renders each page's copy, cards, errors and route map into a JSON
 * island, so no URL or translated string is ever hardcoded here. The request
 * list and the question form share one bundle: whichever mount point is on the
 * page boots.
 */
const LIST_DEFAULTS = {
    appName: 'Studylikepro',
    theme: { preset: 'classic' },
    flash: { cancelled: false },
    requests: [],
    stats: [],
    filters: [],
    copy: {},
    urls: {},
};

const FORM_DEFAULTS = {
    appName: 'Studylikepro',
    csrfToken: '',
    theme: { preset: 'classic' },
    learnerGrade: null,
    timezone: null,
    currencySymbol: '',
    maxAttachments: 3,
    maxWindows: 5,
    maxDescription: 2000,
    minWindowDate: '',
    acceptedImageTypes: 'image/jpeg,image/png,image/webp',
    values: {
        description: '',
        budget: '',
        windows: [{ date: '', from: '18:00', to: '19:00' }],
    },
    errors: {},
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

/**
 * The form island also carries the values and the validation messages the field
 * components render, so those are merged separately and the window rows only
 * fall back to the blank row when the server sent nothing usable back.
 */
function readFormConfig() {
    const config = readConfig('student-request-form-config', FORM_DEFAULTS);
    const values = config.values ?? {};
    const windows = Array.isArray(values.windows) && values.windows.length
        ? values.windows
        : FORM_DEFAULTS.values.windows;

    return {
        ...config,
        values: { ...FORM_DEFAULTS.values, ...values, windows },
        errors: { ...FORM_DEFAULTS.errors, ...(config.errors ?? {}) },
    };
}

const listMount = document.getElementById('student-requests-app');

if (listMount) {
    createApp(RequestsApp, {
        config: readConfig('student-requests-config', LIST_DEFAULTS),
    }).mount(listMount);
}

const formMount = document.getElementById('student-request-form-app');

if (formMount) {
    createApp(CreateRequestApp, { config: readFormConfig() }).mount(formMount);
}
