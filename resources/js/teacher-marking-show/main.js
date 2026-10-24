import { createApp } from 'vue';
import TeacherMarkingShowApp from './TeacherMarkingShowApp.vue';

/**
 * Blade owns every formatted label, file size and download route for
 * /teacher/marking/{order}, then hands them over as a JSON island. Only this
 * page mounts the marking order console.
 */
const DEFAULT_FORM = {
    action: '',
    maxFiles: 10,
    maxFeedback: 5000,
    maxMarks: 50,
    accept: '',
    feedback: '',
    marks: [{ label: '', value: '' }],
    errors: { marked_files: [], feedback: [], marks: [], order: null },
};

const DEFAULT_CONFIG = {
    appName: 'Studylikepro',
    csrfToken: '',
    theme: { preset: 'classic' },
    notices: [],
    order: {
        status: { value: '', label: '', tone: 'slate', icon: 'clipboard' },
        overdue: false,
    },
    files: [],
    canMark: false,
    form: null,
    rubric: {
        criteria: [],
        hasCriteria: false,
        total: 0,
        totalLabel: '0',
        max: 0,
        maxLabel: '0',
    },
    annotations: null,
    draft: { savedAtLabel: null, hasDraft: false },
    stamps: [],
    delivery: null,
    dispute: null,
    copy: {},
    urls: {},
};

function readConfig() {
    const island = document.getElementById('teacher-marking-show-config');

    if (!island || !island.textContent.trim()) {
        return DEFAULT_CONFIG;
    }

    try {
        const parsed = JSON.parse(island.textContent);

        return {
            ...DEFAULT_CONFIG,
            ...parsed,
            theme: { ...DEFAULT_CONFIG.theme, ...(parsed.theme ?? {}) },
            order: { ...DEFAULT_CONFIG.order, ...(parsed.order ?? {}) },
            rubric: { ...DEFAULT_CONFIG.rubric, ...(parsed.rubric ?? {}) },
            draft: { ...DEFAULT_CONFIG.draft, ...(parsed.draft ?? {}) },
            form: parsed.form
                ? {
                    ...DEFAULT_FORM,
                    ...parsed.form,
                    errors: { ...DEFAULT_FORM.errors, ...(parsed.form.errors ?? {}) },
                }
                : null,
            copy: { ...DEFAULT_CONFIG.copy, ...(parsed.copy ?? {}) },
            urls: { ...DEFAULT_CONFIG.urls, ...(parsed.urls ?? {}) },
        };
    } catch (error) {
        console.error('Teacher marking order config could not be parsed; falling back to defaults.', error);

        return DEFAULT_CONFIG;
    }
}

const mountPoint = document.getElementById('teacher-marking-show-app');

if (mountPoint) {
    createApp(TeacherMarkingShowApp, { config: readConfig() }).mount(mountPoint);
}
