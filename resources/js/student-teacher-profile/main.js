import { createApp } from 'vue';
import TeacherProfileApp from './TeacherProfileApp.vue';

/**
 * Blade owns the grade scope, every figure, every translated string and the
 * route map for one teacher and hands them over as a JSON island, so no URL,
 * rate or label is hardcoded here. Only the workspace teacher page mounts this
 * bundle — the public directory keeps its own.
 */
const DEFAULT_CONFIG = {
    appName: 'Studylikepro',
    csrfToken: '',
    theme: { preset: 'classic' },
    grade: null,
    gradeScoped: false,
    teacher: {},
    subjects: [],
    serviceGroups: [],
    availability: { timezone: '', timezoneLabel: '', empty: true, days: [], windowNotice: null, timezoneNotice: null },
    reviews: { count: 0, rating: null, summaryLabel: null, breakdown: [], items: [], showingLabel: null },
    booking: {
        canBook: false,
        scopeLabel: '',
        rate: null,
        perHour: '',
        lessonLength: null,
        notice: null,
        bookUrl: null,
        fallbackUrl: null,
    },
    breadcrumbs: [],
    copy: {},
    urls: {},
};

function readConfig() {
    const island = document.getElementById('student-teacher-profile-config');

    if (!island || !island.textContent.trim()) {
        return DEFAULT_CONFIG;
    }

    try {
        const parsed = JSON.parse(island.textContent);

        return {
            ...DEFAULT_CONFIG,
            ...parsed,
            theme: { ...DEFAULT_CONFIG.theme, ...(parsed.theme ?? {}) },
            availability: { ...DEFAULT_CONFIG.availability, ...(parsed.availability ?? {}) },
            reviews: { ...DEFAULT_CONFIG.reviews, ...(parsed.reviews ?? {}) },
            booking: { ...DEFAULT_CONFIG.booking, ...(parsed.booking ?? {}) },
            copy: { ...DEFAULT_CONFIG.copy, ...(parsed.copy ?? {}) },
            urls: { ...DEFAULT_CONFIG.urls, ...(parsed.urls ?? {}) },
        };
    } catch (error) {
        console.error('Teacher profile config could not be parsed; falling back to defaults.', error);

        return DEFAULT_CONFIG;
    }
}

const mountPoint = document.getElementById('student-teacher-profile-app');

if (mountPoint) {
    createApp(TeacherProfileApp, { config: readConfig() }).mount(mountPoint);
}
