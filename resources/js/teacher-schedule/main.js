import { createApp } from 'vue';
import ScheduleApp from './ScheduleApp.vue';

/**
 * Blade owns the three collections, every formatted time and price, the ranked
 * priority list and the route map for /teacher/schedule, then hands them over as
 * a JSON island. Only this page mounts the schedule.
 */
const DEFAULT_CONFIG = {
    appName: 'Studylikepro',
    csrfToken: '',
    timezone: '',
    theme: { preset: 'classic' },
    flash: null,
    errors: { status: null },
    stats: {},
    next: null,
    priority: [],
    agenda: [],
    holds: [],
    history: [],
    tabs: [],
    copy: {},
    urls: {},
};

function readConfig() {
    const island = document.getElementById('teacher-schedule-config');

    if (!island || !island.textContent.trim()) {
        return DEFAULT_CONFIG;
    }

    try {
        const parsed = JSON.parse(island.textContent);

        return {
            ...DEFAULT_CONFIG,
            ...parsed,
            theme: { ...DEFAULT_CONFIG.theme, ...(parsed.theme ?? {}) },
            errors: { ...DEFAULT_CONFIG.errors, ...(parsed.errors ?? {}) },
            stats: { ...DEFAULT_CONFIG.stats, ...(parsed.stats ?? {}) },
            copy: { ...DEFAULT_CONFIG.copy, ...(parsed.copy ?? {}) },
            urls: { ...DEFAULT_CONFIG.urls, ...(parsed.urls ?? {}) },
        };
    } catch (error) {
        console.error('Teacher schedule config could not be parsed; falling back to defaults.', error);

        return DEFAULT_CONFIG;
    }
}

const mountPoint = document.getElementById('teacher-schedule-app');

if (mountPoint) {
    createApp(ScheduleApp, { config: readConfig() }).mount(mountPoint);
}
