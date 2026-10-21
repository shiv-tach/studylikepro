import { createApp } from 'vue';
import TeacherVerificationApp from './TeacherVerificationApp.vue';

/**
 * Blade owns the application status, the documents on file, the document types,
 * the upload limits, the validation errors and the route map for the
 * verification step, and hands them over as a JSON island, so no label or URL is
 * hardcoded here. Only /teacher/verification mounts this bundle.
 */
const DEFAULT_CONFIG = {
    appName: 'Studylikepro',
    csrfToken: '',
    theme: { preset: 'classic' },
    status: { current: 'draft', label: '' },
    profile: {},
    canEditDocuments: false,
    documentTypes: [],
    documents: [],
    selectedType: 'id_proof',
    highlightDocumentId: null,
    limits: {},
    values: { agree: false },
    errors: {},
    notices: [],
    steps: [],
    currentStep: 2,
    copy: {},
    urls: {},
};

function readConfig() {
    const island = document.getElementById('teacher-verification-config');

    if (!island || !island.textContent.trim()) {
        return DEFAULT_CONFIG;
    }

    try {
        const parsed = JSON.parse(island.textContent);

        return {
            ...DEFAULT_CONFIG,
            ...parsed,
            theme: { ...DEFAULT_CONFIG.theme, ...(parsed.theme ?? {}) },
            status: { ...DEFAULT_CONFIG.status, ...(parsed.status ?? {}) },
            limits: { ...DEFAULT_CONFIG.limits, ...(parsed.limits ?? {}) },
            values: { ...DEFAULT_CONFIG.values, ...(parsed.values ?? {}) },
            copy: { ...DEFAULT_CONFIG.copy, ...(parsed.copy ?? {}) },
            urls: { ...DEFAULT_CONFIG.urls, ...(parsed.urls ?? {}) },
        };
    } catch (error) {
        console.error('Verification config could not be parsed; falling back to defaults.', error);

        return DEFAULT_CONFIG;
    }
}

const mountPoint = document.getElementById('teacher-verification-app');

if (mountPoint) {
    createApp(TeacherVerificationApp, { config: readConfig() }).mount(mountPoint);
}
