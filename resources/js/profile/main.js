import { createApp } from 'vue';
import ProfileApp from './ProfileApp.vue';

/**
 * Blade renders the account values, the email-verification state, every
 * validation bag and the route map into a JSON island, so no field name or URL
 * is hardcoded here. Only /profile mounts this bundle.
 */
const DEFAULT_CONFIG = {
    appName: 'Studylikepro',
    csrfToken: '',
    theme: { preset: 'classic' },
    user: {},
    values: { firstName: '', lastName: '', email: '' },
    errors: {},
    passwordErrors: {},
    deleteErrors: [],
    status: { profileUpdated: false, passwordUpdated: false, verificationLinkSent: false },
    mustVerifyEmail: false,
    showsVerificationNotice: false,
    deleteDialogOpen: false,
    copy: {},
    urls: {},
};

function readConfig() {
    const island = document.getElementById('profile-config');

    if (!island || !island.textContent.trim()) {
        return DEFAULT_CONFIG;
    }

    try {
        const parsed = JSON.parse(island.textContent);

        return {
            ...DEFAULT_CONFIG,
            ...parsed,
            theme: { ...DEFAULT_CONFIG.theme, ...(parsed.theme ?? {}) },
            user: { ...DEFAULT_CONFIG.user, ...(parsed.user ?? {}) },
            values: { ...DEFAULT_CONFIG.values, ...(parsed.values ?? {}) },
            errors: { ...(parsed.errors ?? {}) },
            passwordErrors: { ...(parsed.passwordErrors ?? {}) },
            deleteErrors: Array.isArray(parsed.deleteErrors) ? parsed.deleteErrors : [],
            status: { ...DEFAULT_CONFIG.status, ...(parsed.status ?? {}) },
            copy: { ...DEFAULT_CONFIG.copy, ...(parsed.copy ?? {}) },
            urls: { ...DEFAULT_CONFIG.urls, ...(parsed.urls ?? {}) },
        };
    } catch (error) {
        console.error('Profile config could not be parsed; falling back to defaults.', error);

        return DEFAULT_CONFIG;
    }
}

const mountPoint = document.getElementById('profile-app');

if (mountPoint) {
    createApp(ProfileApp, { config: readConfig() }).mount(mountPoint);
}
