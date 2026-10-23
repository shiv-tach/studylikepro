import { createApp } from 'vue';
import TeacherReviewsApp from './TeacherReviewsApp.vue';

/**
 * Blade owns the rating summary, the star distribution, every formatted review
 * row, the report reasons and the route map for /teacher/reviews, then hands
 * them over as a JSON island. Only this page mounts the review workspace.
 */
const DEFAULT_CONFIG = {
    appName: 'Studylikepro',
    csrfToken: '',
    theme: { preset: 'classic' },
    notices: [],
    stats: { rating: '—', ratingCount: 0, reviewsLabel: '', lessons: 0, reported: 0 },
    breakdown: { total: 0, rows: [] },
    reviews: [],
    flag: { openId: null, reason: null, notes: null, errors: {}, reasons: [], maxNotes: 500 },
    copy: {},
    urls: {},
};

function readConfig() {
    const island = document.getElementById('teacher-reviews-config');

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
            breakdown: { ...DEFAULT_CONFIG.breakdown, ...(parsed.breakdown ?? {}) },
            flag: {
                ...DEFAULT_CONFIG.flag,
                ...(parsed.flag ?? {}),
                errors: { ...DEFAULT_CONFIG.flag.errors, ...(parsed.flag?.errors ?? {}) },
            },
            copy: { ...DEFAULT_CONFIG.copy, ...(parsed.copy ?? {}) },
            urls: { ...DEFAULT_CONFIG.urls, ...(parsed.urls ?? {}) },
        };
    } catch (error) {
        console.error('Teacher reviews config could not be parsed; falling back to defaults.', error);

        return DEFAULT_CONFIG;
    }
}

const mountPoint = document.getElementById('teacher-reviews-app');

if (mountPoint) {
    createApp(TeacherReviewsApp, { config: readConfig() }).mount(mountPoint);
}
