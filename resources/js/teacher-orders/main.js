import { createApp } from 'vue';
import OrdersApp from './OrdersApp.vue';

/**
 * Blade owns the query results, the status counts, the search state, every
 * formatted price and the route map for /teacher/orders, then hands them over as
 * a JSON island. Only this page mounts the order list.
 */
const DEFAULT_CONFIG = {
    appName: 'Studylikepro',
    csrfToken: '',
    theme: { preset: 'classic' },
    stats: {},
    statusTabs: [],
    search: { value: '', clearUrl: '', status: '' },
    purchases: [],
    pagination: { visible: 0, total: 0 },
    filtered: false,
    copy: {},
    urls: {},
};

function readConfig() {
    const island = document.getElementById('teacher-orders-config');

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
            search: { ...DEFAULT_CONFIG.search, ...(parsed.search ?? {}) },
            pagination: { ...DEFAULT_CONFIG.pagination, ...(parsed.pagination ?? {}) },
            copy: { ...DEFAULT_CONFIG.copy, ...(parsed.copy ?? {}) },
            urls: { ...DEFAULT_CONFIG.urls, ...(parsed.urls ?? {}) },
        };
    } catch (error) {
        console.error('Teacher orders config could not be parsed; falling back to defaults.', error);

        return DEFAULT_CONFIG;
    }
}

const mountPoint = document.getElementById('teacher-orders-app');

if (mountPoint) {
    createApp(OrdersApp, { config: readConfig() }).mount(mountPoint);
}
