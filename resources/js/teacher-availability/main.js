import { createApp } from 'vue';
import AvailabilityApp from './AvailabilityApp.vue';

/**
 * Blade owns the saved week, the preset catalog, the default price, every
 * formatted time and price, the validation messages and the route map for
 * /teacher/availability, then hands them over as a JSON island. Only this page
 * mounts the manager (the onboarding step has its own bundle).
 */
const DEFAULT_CONFIG = {
    appName: 'Studylikepro',
    csrfToken: '',
    timezone: '',
    theme: { preset: 'classic' },
    flash: null,
    errors: {},
    errorMessages: [],
    week: [],
    presets: [],
    limits: {},
    pricing: {
        rate: 0,
        rateMinor: 0,
        currency: { symbol: 'Rs', code: 'LKR' },
        limits: { min: 100, max: 100000 },
        durations: [],
        subjects: [],
    },
    preview: [],
    timeOff: [],
    timeOffForm: { starts_on: null, ends_on: null, reason: null },
    copy: {},
    urls: {},
};

function readConfig() {
    const island = document.getElementById('teacher-availability-config');

    if (!island || !island.textContent.trim()) {
        return DEFAULT_CONFIG;
    }

    try {
        const parsed = JSON.parse(island.textContent);

        return {
            ...DEFAULT_CONFIG,
            ...parsed,
            theme: { ...DEFAULT_CONFIG.theme, ...(parsed.theme ?? {}) },
            errors: { ...DEFAULT_CONFIG.errors, ...(parsed.errors ?? {}) },
            limits: { ...DEFAULT_CONFIG.limits, ...(parsed.limits ?? {}) },
            pricing: {
                ...DEFAULT_CONFIG.pricing,
                ...(parsed.pricing ?? {}),
                currency: { ...DEFAULT_CONFIG.pricing.currency, ...(parsed.pricing?.currency ?? {}) },
                limits: { ...DEFAULT_CONFIG.pricing.limits, ...(parsed.pricing?.limits ?? {}) },
            },
            timeOffForm: { ...DEFAULT_CONFIG.timeOffForm, ...(parsed.timeOffForm ?? {}) },
            copy: { ...DEFAULT_CONFIG.copy, ...(parsed.copy ?? {}) },
            urls: { ...DEFAULT_CONFIG.urls, ...(parsed.urls ?? {}) },
        };
    } catch (error) {
        console.error('Availability manager config could not be parsed; falling back to defaults.', error);

        return DEFAULT_CONFIG;
    }
}

const mountPoint = document.getElementById('teacher-availability-app');

if (mountPoint) {
    createApp(AvailabilityApp, { config: readConfig() }).mount(mountPoint);
}
