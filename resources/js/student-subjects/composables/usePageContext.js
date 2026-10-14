import { inject } from 'vue';

/**
 * The subject pages are copy-heavy, so the App components provide their Blade
 * copy block once and every card injects it instead of prop-drilling strings
 * through six levels of components.
 */
export const COPY_KEY = Symbol('student-subjects.copy');
export const CSRF_KEY = Symbol('student-subjects.csrf');

export function useCopy() {
    return inject(COPY_KEY, {});
}

export function useCsrfToken() {
    return inject(CSRF_KEY, '');
}
