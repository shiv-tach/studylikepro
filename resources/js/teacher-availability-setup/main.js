import { createApp } from 'vue';
import App from './App.vue';

/**
 * Blade owns the starting week, the preset catalog, the validation messages and
 * the route map for the availability step, and hands them over as a JSON
 * island, so no time, day label or URL is hardcoded here. Only
 * /teacher/availability/setup mounts this bundle.
 */
const DEFAULT_CONFIG = {
    appName: 'Studylikepro',
    csrfToken: '',
    theme: { preset: 'classic' },
    status: { profileCompleted: false },
    profile: {},
    week: [],
    presets: [],
    limits: {},
    errors: {},
    errorMessages: [],
    steps: [],
    currentStep: 2,
    copy: {},
    urls: {},
};

function readConfig() {
    const island = document.getElementById('teacher-availability-setup-config');

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
            profile: { ...DEFAULT_CONFIG.profile, ...(parsed.profile ?? {}) },
            limits: { ...DEFAULT_CONFIG.limits, ...(parsed.limits ?? {}) },
            copy: { ...DEFAULT_CONFIG.copy, ...(parsed.copy ?? {}) },
            urls: { ...DEFAULT_CONFIG.urls, ...(parsed.urls ?? {}) },
        };
    } catch (error) {
        console.error('Availability setup config could not be parsed; falling back to defaults.', error);

        return DEFAULT_CONFIG;
    }
}

const mountPoint = document.getElementById('teacher-availability-setup-app');

if (mountPoint) {
    createApp(App, { config: readConfig() }).mount(mountPoint);
}
