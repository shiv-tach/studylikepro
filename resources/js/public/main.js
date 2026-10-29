import { createApp } from 'vue';
import { createRevealDirective } from '../landing/reveal';
import SubjectsApp from './SubjectsApp.vue';
import SubjectShowApp from './SubjectShowApp.vue';
import TeacherShowApp from './TeacherShowApp.vue';
import TeachersApp from './TeachersApp.vue';
import ContactApp from './ContactApp.vue';
// The public design system shared with the landing page: tokens, decorative
// layers and native-first chrome. Kept out of app.css so the signed-in product
// never pays for it.
import '../../css/landing.css';

/**
 * Blade renders each public page's copy, route map and display strings into a
 * JSON island, so no URL, price or translated string is ever hardcoded here.
 * The three pages share one bundle: whichever mount point is on the page boots.
 */
const DEFAULTS = {
    appName: 'Studylikepro',
    authenticated: false,
    copy: {},
    urls: {},
    support: {},
    images: { logo: '/images/brand/logo-nav.png' },
};

const PAGE_DEFAULTS = {
    'public-subjects-config': {
        ...DEFAULTS,
        levels: [],
        grades: [],
        level: null,
        grade: null,
        subjectGroups: [],
        groupedByBasket: false,
    },
    'public-subject-config': {
        ...DEFAULTS,
        csrfToken: '',
        documentTitle: '',
        subject: {},
        level: null,
        breadcrumbs: [],
        serviceGroups: [],
        teachers: { items: [], total: 0, directoryUrl: null, directoryRootUrl: null },
        lessonGroups: [],
    },
    'public-teachers-config': {
        ...DEFAULTS,
        filters: {},
        levelOptions: [],
        subjectOptions: [],
        lessonGroups: [],
        gradeGroups: [],
        languageOptions: [],
        weekdayOptions: [],
        sortOptions: [],
        teachers: [],
        total: 0,
        pagination: [],
        error: null,
    },
    'public-teacher-config': {
        ...DEFAULTS,
        teacher: {},
        services: { title: '', badges: [] },
        grades: { summary: null, count: 0, groups: [] },
        availability: {
            timezone: '',
            timezoneLabel: '',
            timezoneNote: '',
            empty: true,
            slotCount: 0,
            slotCountCapped: false,
            days: [],
            windows: [],
            status: { state: 'none', label: '', detail: null },
            lessonMinutes: 0,
        },
        reviews: { count: 0, rating: null, summaryLabel: null, breakdown: [], items: [], showingLabel: null },
        booking: {
            canBook: false,
            startingAt: '',
            rate: null,
            perHour: '',
            lessonLength: null,
            timezoneLabel: '',
            slotsLabel: '',
            action: { label: '', url: null },
            secondary: null,
            trust: [],
        },
        breadcrumbs: [],
    },
    'public-contact-config': {
        ...DEFAULTS,
        csrfToken: '',
        sent: false,
        old: {},
        errors: {},
    },
};

function readConfig(id) {
    const defaults = PAGE_DEFAULTS[id] ?? DEFAULTS;
    const island = document.getElementById(id);

    if (!island || !island.textContent.trim()) {
        return defaults;
    }

    try {
        const parsed = JSON.parse(island.textContent);

        return {
            ...defaults,
            ...parsed,
            copy: { ...defaults.copy, ...(parsed.copy ?? {}) },
            urls: { ...defaults.urls, ...(parsed.urls ?? {}) },
            support: { ...defaults.support, ...(parsed.support ?? {}) },
            images: { ...defaults.images, ...(parsed.images ?? {}) },
        };
    } catch (error) {
        console.error(`${id} could not be parsed; falling back to defaults.`, error);

        return defaults;
    }
}

const pages = [
    { mountId: 'public-subjects-app', configId: 'public-subjects-config', component: SubjectsApp },
    { mountId: 'public-subject-app', configId: 'public-subject-config', component: SubjectShowApp },
    { mountId: 'public-teachers-app', configId: 'public-teachers-config', component: TeachersApp },
    { mountId: 'public-teacher-app', configId: 'public-teacher-config', component: TeacherShowApp },
    { mountId: 'public-contact-app', configId: 'public-contact-config', component: ContactApp },
];

for (const page of pages) {
    const mountPoint = document.getElementById(page.mountId);

    if (mountPoint) {
        createApp(page.component, { config: readConfig(page.configId) })
            .directive('reveal', createRevealDirective())
            .mount(mountPoint);
    }
}
