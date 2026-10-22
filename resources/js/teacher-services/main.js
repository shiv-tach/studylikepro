import { createApp } from 'vue';
import TeacherServicesApp from './TeacherServicesApp.vue';

/**
 * Blade owns the service types, the scope options, the price labels, the
 * validated limits, the old input, the validation errors and the route map for
 * /teacher/services, then hands them over as a JSON island. Only this page
 * mounts the catalog and its composer.
 */
const DEFAULT_CONFIG = {
    appName: 'Studylikepro',
    csrfToken: '',
    theme: { preset: 'classic' },
    notices: [],
    hasSubjects: false,
    stats: {},
    types: [],
    offerings: [],
    options: { grades: [], subjects: [], lessons: [], durations: [] },
    limits: {},
    currency: { symbol: 'Rs', code: 'LKR' },
    form: null,
    openComposer: null,
    editingOfferingId: null,
    errors: {},
    hasErrors: false,
    urls: {},
    copy: {},
};

function readConfig() {
    const island = document.getElementById('teacher-services-config');

    if (!island || !island.textContent.trim()) {
        return DEFAULT_CONFIG;
    }

    try {
        const parsed = JSON.parse(island.textContent);

        return {
            ...DEFAULT_CONFIG,
            ...parsed,
            theme: { ...DEFAULT_CONFIG.theme, ...(parsed.theme ?? {}) },
            stats: { ...DEFAULT_CONFIG.stats, ...(parsed.stats ?? {}) },
            options: { ...DEFAULT_CONFIG.options, ...(parsed.options ?? {}) },
            limits: { ...DEFAULT_CONFIG.limits, ...(parsed.limits ?? {}) },
            currency: { ...DEFAULT_CONFIG.currency, ...(parsed.currency ?? {}) },
            errors: { ...DEFAULT_CONFIG.errors, ...(parsed.errors ?? {}) },
            copy: { ...DEFAULT_CONFIG.copy, ...(parsed.copy ?? {}) },
            urls: { ...DEFAULT_CONFIG.urls, ...(parsed.urls ?? {}) },
        };
    } catch (error) {
        console.error('Teacher services config could not be parsed; falling back to defaults.', error);

        return DEFAULT_CONFIG;
    }
}

const mountPoint = document.getElementById('teacher-services-app');

if (mountPoint) {
    createApp(TeacherServicesApp, { config: readConfig() }).mount(mountPoint);
}
