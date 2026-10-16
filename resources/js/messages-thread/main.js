import { createApp } from 'vue';
import App from './App.vue';

/**
 * Blade renders the first page of messages, the lesson line, the report form's
 * state and the route map into a JSON island, so no URL, timestamp or translated
 * string is hardcoded here. Only `/messages/{conversation}` mounts this bundle.
 */
const DEFAULT_CONFIG = {
    appName: 'Studylikepro',
    csrfToken: '',
    theme: { preset: 'classic' },
    counterpart: '',
    initials: '',
    lesson: null,
    messages: [],
    flash: null,
    report: { open: false, errors: {}, old: {}, reasons: [] },
    copy: {},
    urls: {},
};

function readConfig() {
    const island = document.getElementById('messages-thread-config');

    if (!island || !island.textContent.trim()) {
        return DEFAULT_CONFIG;
    }

    try {
        const parsed = JSON.parse(island.textContent);

        return {
            ...DEFAULT_CONFIG,
            ...parsed,
            theme: { ...DEFAULT_CONFIG.theme, ...(parsed.theme ?? {}) },
            messages: Array.isArray(parsed.messages) ? parsed.messages : [],
            report: { ...DEFAULT_CONFIG.report, ...(parsed.report ?? {}) },
            copy: { ...DEFAULT_CONFIG.copy, ...(parsed.copy ?? {}) },
            urls: { ...DEFAULT_CONFIG.urls, ...(parsed.urls ?? {}) },
        };
    } catch (error) {
        console.error('Thread config could not be parsed; falling back to defaults.', error);

        return DEFAULT_CONFIG;
    }
}

const mountPoint = document.getElementById('messages-thread-app');

if (mountPoint) {
    createApp(App, { config: readConfig() }).mount(mountPoint);
}
