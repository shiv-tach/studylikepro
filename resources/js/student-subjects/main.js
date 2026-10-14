import { createApp } from 'vue';
import InterestsApp from './InterestsApp.vue';
import SubjectApp from './SubjectApp.vue';

/**
 * Blade renders each page's copy, subject cards and route map into a JSON
 * island, so no URL, price or translated string is ever hardcoded here. The
 * two pages share one bundle: whichever mount point is on the page boots.
 */
const INTERESTS_DEFAULTS = {
    appName: 'Studylikepro',
    theme: { preset: 'classic' },
    gradeLabel: null,
    hasBaskets: false,
    groups: [],
    copy: {},
    flash: { updated: false, basketReminder: null },
    urls: {},
};

const SUBJECT_DEFAULTS = {
    appName: 'Studylikepro',
    csrfToken: '',
    theme: { preset: 'classic' },
    subject: { name: '', icon: '📘', description: null, lessonLabel: '', basket: null, grade: null },
    copy: {},
    serviceGroups: [],
    teachers: { items: [], total: 0, directoryUrl: null, directoryRootUrl: null },
    lessonGroups: [],
    breadcrumbs: [],
    urls: {},
};

function readConfig(id, defaults) {
    const island = document.getElementById(id);

    if (!island || !island.textContent.trim()) {
        return defaults;
    }

    try {
        const parsed = JSON.parse(island.textContent);

        return {
            ...defaults,
            ...parsed,
            theme: { ...defaults.theme, ...(parsed.theme ?? {}) },
            copy: { ...defaults.copy, ...(parsed.copy ?? {}) },
            urls: { ...defaults.urls, ...(parsed.urls ?? {}) },
        };
    } catch (error) {
        console.error(`${id} could not be parsed; falling back to defaults.`, error);

        return defaults;
    }
}

const interestsMount = document.getElementById('student-interests-app');

if (interestsMount) {
    createApp(InterestsApp, {
        config: readConfig('student-interests-config', INTERESTS_DEFAULTS),
    }).mount(interestsMount);
}

const subjectMount = document.getElementById('student-subject-app');

if (subjectMount) {
    createApp(SubjectApp, {
        config: readConfig('student-subject-config', SUBJECT_DEFAULTS),
    }).mount(subjectMount);
}
