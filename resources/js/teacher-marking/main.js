import { createApp } from 'vue';
import TeacherMarkingApp from './TeacherMarkingApp.vue';

/**
 * Blade owns the status rail counts, every formatted amount and date and the
 * route map for /teacher/marking, then hands them over as a JSON island. Only
 * this page mounts the marking inbox.
 */
const DEFAULT_CONFIG = {
    appName: 'Studylikepro',
    theme: { preset: 'classic' },
    notices: [],
    stats: { awaiting: 0, overdue: 0, returned: 0 },
    statusTabs: [],
    orders: [],
    pagination: { visible: 0, total: 0 },
    copy: {},
    urls: {},
};

function readConfig() {
    const island = document.getElementById('teacher-marking-config');

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
            pagination: { ...DEFAULT_CONFIG.pagination, ...(parsed.pagination ?? {}) },
            copy: { ...DEFAULT_CONFIG.copy, ...(parsed.copy ?? {}) },
            urls: { ...DEFAULT_CONFIG.urls, ...(parsed.urls ?? {}) },
        };
    } catch (error) {
        console.error('Teacher marking config could not be parsed; falling back to defaults.', error);

        return DEFAULT_CONFIG;
    }
}

const mountPoint = document.getElementById('teacher-marking-app');

if (mountPoint) {
    createApp(TeacherMarkingApp, { config: readConfig() }).mount(mountPoint);
}
