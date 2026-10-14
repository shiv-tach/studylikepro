import { inject } from 'vue';

/**
 * The request pages are copy-heavy, so each App component provides its Blade
 * copy block, route map and CSRF token once and every card injects them
 * instead of prop-drilling strings through every level.
 */
export const COPY_KEY = Symbol('student-requests.copy');
export const URLS_KEY = Symbol('student-requests.urls');
export const CSRF_KEY = Symbol('student-requests.csrf');

export function useCopy() {
    return inject(COPY_KEY, {});
}

export function useUrls() {
    return inject(URLS_KEY, {});
}

export function useCsrfToken() {
    return inject(CSRF_KEY, '');
}
