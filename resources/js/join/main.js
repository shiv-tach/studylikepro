import { createApp } from 'vue';
import SurveyRail from './components/SurveyRail.vue';

/**
 * The invite survey renders in Vue one question per page (`/join/level` …
 * `/join/proof`) plus the account step (`/join/account`). Blade still owns the
 * data — answers, options, recap, rail, validation messages, routes and CSRF —
 * and hands it over as one JSON island per screen.
 *
 * The question and account apps are imported on demand, so a question screen
 * never downloads the SMS code form and `/join/account` never downloads nine
 * question components.
 */
const DEFAULTS = {
    csrfToken: '',
    step: null,
    isProof: false,
    index: 0,
    total: 9,
    answered: 0,
    percent: 0,
    questions: 9,
    currentGroup: 1,
    title: '',
    body: '',
    values: {},
    errors: {},
    fieldErrors: {},
    errorList: [],
    rail: [],
    summary: [],
    options: {},
    limits: {},
    currency: { symbol: 'Rs', code: 'LKR' },
    urls: {},
    copy: {},
    questionCopy: {},
    status: { resent: false, sendFailed: false },
};

function readConfig(id) {
    const island = document.getElementById(id);

    if (!island || !island.textContent.trim()) {
        return DEFAULTS;
    }

    try {
        const parsed = JSON.parse(island.textContent);

        return {
            ...DEFAULTS,
            ...parsed,
            values: { ...DEFAULTS.values, ...(parsed.values ?? {}) },
            errors: { ...DEFAULTS.errors, ...(parsed.errors ?? {}) },
            fieldErrors: { ...DEFAULTS.fieldErrors, ...(parsed.fieldErrors ?? {}) },
            options: { ...DEFAULTS.options, ...(parsed.options ?? {}) },
            limits: { ...DEFAULTS.limits, ...(parsed.limits ?? {}) },
            currency: { ...DEFAULTS.currency, ...(parsed.currency ?? {}) },
            urls: { ...DEFAULTS.urls, ...(parsed.urls ?? {}) },
            copy: { ...DEFAULTS.copy, ...(parsed.copy ?? {}) },
            questionCopy: { ...DEFAULTS.questionCopy, ...(parsed.questionCopy ?? {}) },
            status: { ...DEFAULTS.status, ...(parsed.status ?? {}) },
        };
    } catch (error) {
        console.error(`${id} could not be parsed; falling back to defaults.`, error);

        return DEFAULTS;
    }
}

const surveyMount = document.getElementById('join-survey-app');
const accountMount = document.getElementById('join-account-app');

// The rail lives in the onboarding layout's sticky header (its slot is filled
// with `#join-rail`), so it mounts from the same config as the page body.
const pageId = surveyMount ? 'join-survey-config' : 'join-account-config';
const pageConfig = surveyMount || accountMount ? readConfig(pageId) : null;

const railMount = document.getElementById('join-rail');

if (railMount && pageConfig) {
    createApp(SurveyRail, { config: pageConfig }).mount(railMount);
}

async function boot(mount, id, load) {
    if (!mount) {
        return;
    }

    const { default: App } = await load();

    createApp(App, { config: readConfig(id) }).mount(mount);
}

boot(surveyMount, 'join-survey-config', () => import('./SurveyApp.vue'));
boot(accountMount, 'join-account-config', () => import('./AccountApp.vue'));
