import { inject } from 'vue';

/**
 * ProfileApp.vue provides Blade's copy block, route map and CSRF token once; the
 * three account cards and the delete dialog inject them.
 */
export const COPY_KEY = Symbol('profile.copy');
export const URLS_KEY = Symbol('profile.urls');
export const CSRF_KEY = Symbol('profile.csrf');

export function useCopy() {
    return inject(COPY_KEY, {});
}

export function useUrls() {
    return inject(URLS_KEY, {});
}

export function useCsrfToken() {
    return inject(CSRF_KEY, '');
}
