import { inject } from 'vue';

/**
 * SettingsThemeApp.vue provides Blade's copy block, route map and CSRF token
 * once; each picker injects them instead of prop-drilling strings.
 */
export const COPY_KEY = Symbol('settings-theme.copy');
export const URLS_KEY = Symbol('settings-theme.urls');
export const CSRF_KEY = Symbol('settings-theme.csrf');

export function useCopy() {
    return inject(COPY_KEY, {});
}

export function useUrls() {
    return inject(URLS_KEY, {});
}

export function useCsrfToken() {
    return inject(CSRF_KEY, '');
}
