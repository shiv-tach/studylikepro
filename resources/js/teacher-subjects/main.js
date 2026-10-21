import { createApp } from 'vue';
import TeacherSubjectsApp from './TeacherSubjectsApp.vue';

/**
 * Blade owns the approved subjects' grade/lesson/rate setup, the pending and
 * rejected queues, the catalog picker, the propose form, the validation errors
 * and the route map for /teacher/subjects, then hands them over as a JSON
 * island, so no label or URL is hardcoded here. Only this page mounts the app.
 */
const DEFAULT_CONFIG = {
    appName: 'Studylikepro',
    csrfToken: '',
    theme: { preset: 'classic' },
    notices: [],
    teacher: {},
    stats: {},
    subjects: [],
    pending: [],
    rejected: [],
    catalog: { levels: [], defaultLevelKey: null, hasSubjects: false },
    propose: { levels: [], limits: {}, values: {}, errors: {} },
    subjectIdErrors: [],
    editingSubjectId: null,
    openSubjectId: null,
    initialTab: 'my',
    addSection: 'catalog',
    urls: {},
    copy: {},
};

function readConfig() {
    const island = document.getElementById('teacher-subjects-config');

    if (!island || !island.textContent.trim()) {
        return DEFAULT_CONFIG;
    }

    try {
        const parsed = JSON.parse(island.textContent);

        return {
            ...DEFAULT_CONFIG,
            ...parsed,
            theme: { ...DEFAULT_CONFIG.theme, ...(parsed.theme ?? {}) },
            teacher: { ...DEFAULT_CONFIG.teacher, ...(parsed.teacher ?? {}) },
            stats: { ...DEFAULT_CONFIG.stats, ...(parsed.stats ?? {}) },
            catalog: { ...DEFAULT_CONFIG.catalog, ...(parsed.catalog ?? {}) },
            propose: {
                ...DEFAULT_CONFIG.propose,
                ...(parsed.propose ?? {}),
                values: { ...DEFAULT_CONFIG.propose.values, ...(parsed.propose?.values ?? {}) },
                errors: { ...DEFAULT_CONFIG.propose.errors, ...(parsed.propose?.errors ?? {}) },
                limits: { ...DEFAULT_CONFIG.propose.limits, ...(parsed.propose?.limits ?? {}) },
            },
            copy: { ...DEFAULT_CONFIG.copy, ...(parsed.copy ?? {}) },
            urls: { ...DEFAULT_CONFIG.urls, ...(parsed.urls ?? {}) },
        };
    } catch (error) {
        console.error('Teacher subjects config could not be parsed; falling back to defaults.', error);

        return DEFAULT_CONFIG;
    }
}

const mountPoint = document.getElementById('teacher-subjects-app');

if (mountPoint) {
    createApp(TeacherSubjectsApp, { config: readConfig() }).mount(mountPoint);
}
