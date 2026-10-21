import { inject } from 'vue';

/**
 * The verification page is copy-heavy, so TeacherVerificationApp.vue provides
 * Blade's copy block, route map and CSRF token once and every card injects them
 * instead of prop-drilling strings through each level of components.
 */
export const COPY_KEY = Symbol('teacher-verification.copy');
export const URLS_KEY = Symbol('teacher-verification.urls');
export const CSRF_KEY = Symbol('teacher-verification.csrf');

export function useCopy() {
    return inject(COPY_KEY, {});
}

export function useUrls() {
    return inject(URLS_KEY, {});
}

export function useCsrfToken() {
    return inject(CSRF_KEY, '');
}

/**
 * Fill a Blade sentence that carries a `:placeholder` the server cannot know —
 * the step counter, the progress percentage and the file size both depend on
 * what the page currently holds.
 */
export function format(template, values) {
    return Object.entries(values).reduce(
        (text, [key, value]) => text.replace(`:${key}`, String(value)),
        String(template ?? ''),
    );
}
