import { createApp } from 'vue';
import App from './App.vue';

/**
 * Blade renders the tabs, the filter options, the lesson cards and the route
 * map into a JSON island, so no URL, figure or translated string is hardcoded
 * here. Only the "My lessons" page mounts this bundle.
 */
const DEFAULT_CONFIG = {
    appName: 'Studylikepro',
    theme: { preset: 'classic' },
    flash: { rescheduled: false },
    tab: 'upcoming',
    tabs: [],
    filters: { status: '', subject: '', teacher: '', from: '', to: '' },
    statusOptions: [],
    subjectOptions: [],
    teacherOptions: [],
    hasActiveFilters: false,
    matchLabel: null,
    lessons: [],
    copy: {},
    urls: {},
};

function readConfig() {
    const island = document.getElementById('student-lessons-config');

    if (!island || !island.textContent.trim()) {
        return DEFAULT_CONFIG;
    }

    try {
        const parsed = JSON.parse(island.textContent);

        return {
            ...DEFAULT_CONFIG,
            ...parsed,
            theme: { ...DEFAULT_CONFIG.theme, ...(parsed.theme ?? {}) },
            flash: { ...DEFAULT_CONFIG.flash, ...(parsed.flash ?? {}) },
            filters: { ...DEFAULT_CONFIG.filters, ...(parsed.filters ?? {}) },
            copy: { ...DEFAULT_CONFIG.copy, ...(parsed.copy ?? {}) },
            urls: { ...DEFAULT_CONFIG.urls, ...(parsed.urls ?? {}) },
        };
    } catch (error) {
        console.error('Lessons config could not be parsed; falling back to defaults.', error);

        return DEFAULT_CONFIG;
    }
}

const mountPoint = document.getElementById('student-lessons-app');

if (mountPoint) {
    createApp(App, { config: readConfig() }).mount(mountPoint);
}
