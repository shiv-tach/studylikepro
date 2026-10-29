import { createApp } from 'vue';
import TeacherProfileApp from './TeacherProfileApp.vue';

/**
 * Blade owns the language catalog, the stored answers, the setup steps, the
 * validation errors and the route map for the teaching profile, and hands them
 * over as a JSON island, so no label or URL is hardcoded here. Only
 * /teacher/profile mounts this bundle.
 */
const DEFAULT_CONFIG = {
    appName: 'Studylikepro',
    csrfToken: '',
    theme: { preset: 'classic' },
    status: { updated: false, incomplete: false },
    teacher: {},
    profile: {},
    languages: [],
    limits: {},
    values: {},
    preview: {},
    steps: [],
    errors: {},
    copy: {},
    urls: {},
};

function readConfig() {
    const island = document.getElementById('teacher-profile-config');

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
            preview: { ...DEFAULT_CONFIG.preview, ...(parsed.preview ?? {}) },
            copy: { ...DEFAULT_CONFIG.copy, ...(parsed.copy ?? {}) },
            urls: { ...DEFAULT_CONFIG.urls, ...(parsed.urls ?? {}) },
        };
    } catch (error) {
        console.error('Teaching profile config could not be parsed; falling back to defaults.', error);

        return DEFAULT_CONFIG;
    }
}

const mountPoint = document.getElementById('teacher-profile-app');

if (mountPoint) {
    createApp(TeacherProfileApp, { config: readConfig() }).mount(mountPoint);
}
