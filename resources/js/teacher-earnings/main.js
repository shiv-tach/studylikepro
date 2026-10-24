import { createApp } from 'vue';
import EarningsApp from './EarningsApp.vue';

/**
 * Blade owns the totals, every formatted amount, the status tabs, the ledger
 * page, the six-month trend and the route map for /teacher/earnings, then hands
 * them over as a JSON island. Only this page mounts the earnings dashboard.
 */
const DEFAULT_CONFIG = {
    appName: 'Studylikepro',
    theme: { preset: 'classic' },
    money: { prefix: '', decimals: 2 },
    commissionPercent: 0,
    stats: { tiles: [], reversed: { minor: 0, label: null } },
    statusTabs: [],
    ledger: [],
    trend: { max: 0, totalLabel: '', months: [] },
    payouts: [],
    pagination: { visible: 0, total: 0, page: 1, lastPage: 1, filtered: false },
    copy: {},
    urls: {},
};

function readConfig() {
    const island = document.getElementById('teacher-earnings-config');

    if (!island || !island.textContent.trim()) {
        return DEFAULT_CONFIG;
    }

    try {
        const parsed = JSON.parse(island.textContent);

        return {
            ...DEFAULT_CONFIG,
            ...parsed,
            theme: { ...DEFAULT_CONFIG.theme, ...(parsed.theme ?? {}) },
            money: { ...DEFAULT_CONFIG.money, ...(parsed.money ?? {}) },
            stats: { ...DEFAULT_CONFIG.stats, ...(parsed.stats ?? {}) },
            trend: { ...DEFAULT_CONFIG.trend, ...(parsed.trend ?? {}) },
            pagination: { ...DEFAULT_CONFIG.pagination, ...(parsed.pagination ?? {}) },
            copy: { ...DEFAULT_CONFIG.copy, ...(parsed.copy ?? {}) },
            urls: { ...DEFAULT_CONFIG.urls, ...(parsed.urls ?? {}) },
        };
    } catch (error) {
        console.error('Teacher earnings config could not be parsed; falling back to defaults.', error);

        return DEFAULT_CONFIG;
    }
}

const mountPoint = document.getElementById('teacher-earnings-app');

if (mountPoint) {
    createApp(EarningsApp, { config: readConfig() }).mount(mountPoint);
}
