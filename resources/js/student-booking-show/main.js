import { createApp } from 'vue';
import LessonShowApp from './LessonShowApp.vue';

/**
 * Blade owns the lesson's state — the banners, the timeline, the money, the
 * payment record, the review and which actions the student may take — and hands
 * them over as a JSON island, so no URL, figure or translated string is ever
 * hardcoded here. Only the student's lesson detail page mounts this bundle.
 */
const DEFAULT_CONFIG = {
    appName: 'Studylikepro',
    csrfToken: '',
    theme: { preset: 'classic' },
    flash: null,
    error: null,
    join: null,
    banner: null,
    details: {},
    timeline: { steps: [], cancellationReason: null },
    money: {},
    actions: [],
    cancelNotice: null,
    cancel: null,
    review: null,
    copy: {},
    urls: {},
};

function readConfig() {
    const island = document.getElementById('student-booking-show-config');

    if (!island || !island.textContent.trim()) {
        return DEFAULT_CONFIG;
    }

    try {
        const parsed = JSON.parse(island.textContent);

        return {
            ...DEFAULT_CONFIG,
            ...parsed,
            theme: { ...DEFAULT_CONFIG.theme, ...(parsed.theme ?? {}) },
            timeline: { ...DEFAULT_CONFIG.timeline, ...(parsed.timeline ?? {}) },
            money: { ...DEFAULT_CONFIG.money, ...(parsed.money ?? {}) },
            copy: { ...DEFAULT_CONFIG.copy, ...(parsed.copy ?? {}) },
            urls: { ...DEFAULT_CONFIG.urls, ...(parsed.urls ?? {}) },
        };
    } catch (error) {
        console.error('Lesson detail config could not be parsed; falling back to defaults.', error);

        return DEFAULT_CONFIG;
    }
}

const mountPoint = document.getElementById('student-booking-show-app');

if (mountPoint) {
    createApp(LessonShowApp, { config: readConfig() }).mount(mountPoint);
}
