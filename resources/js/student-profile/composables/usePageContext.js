import { inject } from 'vue';

/**
 * The learning profile is copy-heavy, so StudentProfileApp.vue provides Blade's
 * copy block, route map and CSRF token once and every card injects them instead
 * of prop-drilling strings through each level of components.
 */
export const COPY_KEY = Symbol('student-profile.copy');
export const URLS_KEY = Symbol('student-profile.urls');
export const CSRF_KEY = Symbol('student-profile.csrf');

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
 * the profile strength percentage is computed from what the student has typed.
 */
export function format(template, values) {
    return Object.entries(values).reduce(
        (text, [key, value]) => text.replace(`:${key}`, String(value)),
        String(template ?? ''),
    );
}
