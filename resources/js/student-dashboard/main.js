import { createApp } from 'vue';
import App from './App.vue';

// Blade renders the route map, the student's numbers and the lesson cards into a
// JSON island so no URL or figure is hardcoded in the Vue app.
const DEFAULT_CONFIG = {
    appName: 'Studylikepro',
    user: { name: '', grade: null, timezone: null, learningLanguage: null },
    theme: { preset: 'classic' },
    flash: { profileCompleted: false },
    stats: {
        subjects: 0,
        upcomingLessons: 0,
        completedLessons: 0,
        reviewedLessons: 0,
    },
    nextLesson: null,
    awaitingReview: null,
    quickActions: [],
    urls: {},
};

function readConfig() {
    const island = document.getElementById('student-dashboard-config');

    if (!island || !island.textContent.trim()) {
        return DEFAULT_CONFIG;
    }

    try {
        const parsed = JSON.parse(island.textContent);

        return {
            ...DEFAULT_CONFIG,
            ...parsed,
            user: { ...DEFAULT_CONFIG.user, ...(parsed.user ?? {}) },
            theme: { ...DEFAULT_CONFIG.theme, ...(parsed.theme ?? {}) },
            flash: { ...DEFAULT_CONFIG.flash, ...(parsed.flash ?? {}) },
            stats: { ...DEFAULT_CONFIG.stats, ...(parsed.stats ?? {}) },
            urls: { ...DEFAULT_CONFIG.urls, ...(parsed.urls ?? {}) },
        };
    } catch (error) {
        console.error('Dashboard config could not be parsed; falling back to defaults.', error);

        return DEFAULT_CONFIG;
    }
}

const mountPoint = document.getElementById('student-dashboard-app');

if (mountPoint) {
    createApp(App, { config: readConfig() }).mount(mountPoint);
}
