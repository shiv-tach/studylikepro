import { inject } from 'vue';

/**
 * The lesson detail page is copy- and status-heavy, so LessonShowApp.vue provides
 * Blade's copy block, route map and CSRF token once and every panel injects them
 * instead of prop-drilling strings through each level of components.
 */
export const COPY_KEY = Symbol('student-booking-show.copy');
export const URLS_KEY = Symbol('student-booking-show.urls');
export const CSRF_KEY = Symbol('student-booking-show.csrf');

export function useCopy() {
    return inject(COPY_KEY, {});
}

export function useUrls() {
    return inject(URLS_KEY, {});
}

export function useCsrfToken() {
    return inject(CSRF_KEY, '');
}
