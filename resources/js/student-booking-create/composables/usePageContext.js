import { inject } from 'vue';

/**
 * The booking stepper is copy- and figure-heavy, so LessonBookApp.vue provides
 * Blade's copy block, route map and CSRF token once and every card injects them
 * instead of prop-drilling strings through each level of components.
 */
export const COPY_KEY = Symbol('student-booking-create.copy');
export const URLS_KEY = Symbol('student-booking-create.urls');
export const CSRF_KEY = Symbol('student-booking-create.csrf');

export function useCopy() {
    return inject(COPY_KEY, {});
}

export function useUrls() {
    return inject(URLS_KEY, {});
}

export function useCsrfToken() {
    return inject(CSRF_KEY, '');
}
