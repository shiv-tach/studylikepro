import { createApp } from 'vue';
import OrderShowApp from './OrderShowApp.vue';

/**
 * Blade owns the order, its money, its plan balance, its sessions, its marking
 * progress, the student's public details and every route for
 * /teacher/orders/{purchase}, then hands them over as one JSON island. Only this
 * page mounts the panels.
 */
const DEFAULT_CONFIG = {
    appName: 'Studylikepro',
    csrfToken: '',
    theme: { preset: 'classic' },
    order: {},
    statusNote: null,
    timeline: [],
    money: { rows: [], payout: {}, payment: null, earning: null },
    plan: null,
    student: {},
    offering: {},
    sessions: [],
    marking: null,
    copy: {},
    urls: {},
};

function readConfig() {
    const island = document.getElementById('teacher-order-show-config');

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
            money: { ...DEFAULT_CONFIG.money, ...(parsed.money ?? {}) },
            student: { ...DEFAULT_CONFIG.student, ...(parsed.student ?? {}) },
            offering: { ...DEFAULT_CONFIG.offering, ...(parsed.offering ?? {}) },
            copy: { ...DEFAULT_CONFIG.copy, ...(parsed.copy ?? {}) },
            urls: { ...DEFAULT_CONFIG.urls, ...(parsed.urls ?? {}) },
        };
    } catch (error) {
        console.error('Teacher order config could not be parsed; falling back to defaults.', error);

        return DEFAULT_CONFIG;
    }
}

const mountPoint = document.getElementById('teacher-order-show-app');

if (mountPoint) {
    createApp(OrderShowApp, { config: readConfig() }).mount(mountPoint);
}
