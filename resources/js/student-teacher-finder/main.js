import { createApp } from 'vue';
import App from './App.vue';

/**
 * Blade renders the grade banner, the filter options, the pre-formatted teacher
 * cards and the route map into a JSON island, so no URL, figure or translated
 * string is hardcoded here. Only the "Find a teacher" page mounts this bundle.
 */
const DEFAULT_CONFIG = {
    appName: 'Studylikepro',
    theme: { preset: 'classic' },
    grade: null,
    filters: { subject: '', language: '', sort: 'available_soon', date: '', time_from: '', time_to: '' },
    subjectOptions: [],
    languageOptions: [],
    sortOptions: [],
    today: '',
    total: 0,
    totalLabel: null,
    hasActiveFilters: false,
    hasDateFilter: false,
    errorMessage: null,
    teachers: [],
    copy: {},
    urls: {},
};

function readConfig() {
    const island = document.getElementById('student-teacher-finder-config');

    if (!island || !island.textContent.trim()) {
        return DEFAULT_CONFIG;
    }

    try {
        const parsed = JSON.parse(island.textContent);

        return {
            ...DEFAULT_CONFIG,
            ...parsed,
            theme: { ...DEFAULT_CONFIG.theme, ...(parsed.theme ?? {}) },
            filters: { ...DEFAULT_CONFIG.filters, ...(parsed.filters ?? {}) },
            copy: { ...DEFAULT_CONFIG.copy, ...(parsed.copy ?? {}) },
            urls: { ...DEFAULT_CONFIG.urls, ...(parsed.urls ?? {}) },
        };
    } catch (error) {
        console.error('Teacher finder config could not be parsed; falling back to defaults.', error);

        return DEFAULT_CONFIG;
    }
}

const mountPoint = document.getElementById('student-teacher-finder-app');

if (mountPoint) {
    createApp(App, { config: readConfig() }).mount(mountPoint);
}
