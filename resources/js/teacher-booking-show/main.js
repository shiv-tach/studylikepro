import { createApp } from 'vue';
import TeacherBookingShowApp from './TeacherBookingShowApp.vue';

/**
 * Blade owns every permission, formatted time and price for one lesson, then
 * hands them over as a JSON island. Only this page mounts the detail view.
 */
const DEFAULT_CONFIG = {
    appName: 'Studylikepro',
    csrfToken: '',
    timezone: '',
    theme: { preset: 'classic' },
    viewer: { isOwner: false, isAdmin: false },
    flash: null,
    error: null,
    header: {},
    lesson: {},
    join: {},
    next: {},
    priority: [],
    journey: [],
    money: {},
    timeline: { steps: [], cancellationReason: null },
    actions: {},
    cancel: null,
    refreshAt: null,
    copy: {},
    urls: {},
};

function readConfig() {
    const island = document.getElementById('teacher-booking-show-config');

    if (!island || !island.textContent.trim()) {
        return DEFAULT_CONFIG;
    }

    try {
        const parsed = JSON.parse(island.textContent);

        return {
            ...DEFAULT_CONFIG,
            ...parsed,
            theme: { ...DEFAULT_CONFIG.theme, ...(parsed.theme ?? {}) },
            viewer: { ...DEFAULT_CONFIG.viewer, ...(parsed.viewer ?? {}) },
            header: { ...DEFAULT_CONFIG.header, ...(parsed.header ?? {}) },
            lesson: { ...DEFAULT_CONFIG.lesson, ...(parsed.lesson ?? {}) },
            join: { ...DEFAULT_CONFIG.join, ...(parsed.join ?? {}) },
            next: { ...DEFAULT_CONFIG.next, ...(parsed.next ?? {}) },
            money: { ...DEFAULT_CONFIG.money, ...(parsed.money ?? {}) },
            timeline: { ...DEFAULT_CONFIG.timeline, ...(parsed.timeline ?? {}) },
            actions: { ...DEFAULT_CONFIG.actions, ...(parsed.actions ?? {}) },
            copy: { ...DEFAULT_CONFIG.copy, ...(parsed.copy ?? {}) },
            urls: { ...DEFAULT_CONFIG.urls, ...(parsed.urls ?? {}) },
        };
    } catch (error) {
        console.error('Teacher booking config could not be parsed; falling back to defaults.', error);

        return DEFAULT_CONFIG;
    }
}

const mountPoint = document.getElementById('teacher-booking-show-app');

if (mountPoint) {
    createApp(TeacherBookingShowApp, { config: readConfig() }).mount(mountPoint);
}
