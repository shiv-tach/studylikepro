import { createApp } from 'vue';
import LessonBookApp from './LessonBookApp.vue';

/**
 * Blade owns the grade scope, the lessons, every price, every translated string
 * and the route map for the booking stepper, then hands them over as a JSON
 * island — so no URL, figure or label is hardcoded here. Only the direct
 * booking page mounts this bundle.
 */
const DEFAULT_CONFIG = {
    appName: 'Studylikepro',
    csrfToken: '',
    theme: { preset: 'classic' },
    teacher: {},
    subjects: [],
    lessons: [],
    durations: [],
    selection: { subjectId: '', lessonId: '', duration: '' },
    lessonsNotice: null,
    timezone: '',
    holdNotice: '',
    slots: [],
    slotsNotice: null,
    selectedSlot: null,
    learner: {},
    errors: { startsAt: null, learnerName: null },
    summary: {},
    copy: {},
    urls: {},
};

function readConfig() {
    const island = document.getElementById('student-booking-create-config');

    if (!island || !island.textContent.trim()) {
        return DEFAULT_CONFIG;
    }

    try {
        const parsed = JSON.parse(island.textContent);

        return {
            ...DEFAULT_CONFIG,
            ...parsed,
            theme: { ...DEFAULT_CONFIG.theme, ...(parsed.theme ?? {}) },
            selection: { ...DEFAULT_CONFIG.selection, ...(parsed.selection ?? {}) },
            learner: { ...DEFAULT_CONFIG.learner, ...(parsed.learner ?? {}) },
            errors: { ...DEFAULT_CONFIG.errors, ...(parsed.errors ?? {}) },
            summary: { ...DEFAULT_CONFIG.summary, ...(parsed.summary ?? {}) },
            copy: { ...DEFAULT_CONFIG.copy, ...(parsed.copy ?? {}) },
            urls: { ...DEFAULT_CONFIG.urls, ...(parsed.urls ?? {}) },
        };
    } catch (error) {
        console.error('Booking stepper config could not be parsed; falling back to defaults.', error);

        return DEFAULT_CONFIG;
    }
}

const mountPoint = document.getElementById('student-booking-create-app');

if (mountPoint) {
    createApp(LessonBookApp, { config: readConfig() }).mount(mountPoint);
}
