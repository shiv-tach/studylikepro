import { createApp } from 'vue';
import StudentProfileApp from './StudentProfileApp.vue';

/**
 * Blade owns the catalog, the student's own answers, the validation errors and
 * the route map for the learning profile and hands them over as a JSON island,
 * so no grade, language or URL is hardcoded here. Only /student/profile mounts
 * this bundle.
 */
const DEFAULT_CONFIG = {
    appName: 'Studylikepro',
    csrfToken: '',
    theme: { preset: 'classic' },
    student: {},
    levels: [],
    languages: [],
    limits: {},
    values: {},
    errors: {},
    status: { updated: false, invalid: false },
    copy: {},
    urls: {},
};

function readConfig() {
    const island = document.getElementById('student-profile-config');

    if (!island || !island.textContent.trim()) {
        return DEFAULT_CONFIG;
    }

    try {
        const parsed = JSON.parse(island.textContent);

        return {
            ...DEFAULT_CONFIG,
            ...parsed,
            theme: { ...DEFAULT_CONFIG.theme, ...(parsed.theme ?? {}) },
            status: { ...DEFAULT_CONFIG.status, ...(parsed.status ?? {}) },
            copy: { ...DEFAULT_CONFIG.copy, ...(parsed.copy ?? {}) },
            urls: { ...DEFAULT_CONFIG.urls, ...(parsed.urls ?? {}) },
        };
    } catch (error) {
        console.error('Learning profile config could not be parsed; falling back to defaults.', error);

        return DEFAULT_CONFIG;
    }
}

const mountPoint = document.getElementById('student-profile-app');

if (mountPoint) {
    createApp(StudentProfileApp, { config: readConfig() }).mount(mountPoint);
}
