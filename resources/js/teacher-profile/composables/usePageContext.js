import { inject } from 'vue';

/**
 * The teaching profile is copy-heavy, so TeacherProfileApp.vue provides Blade's
 * copy block, route map and CSRF token once and every card injects them instead
 * of prop-drilling strings through each level of components.
 */
export const COPY_KEY = Symbol('teacher-profile.copy');
export const URLS_KEY = Symbol('teacher-profile.urls');
export const CSRF_KEY = Symbol('teacher-profile.csrf');

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
 * the step counter and the live rate hint both depend on what the page holds.
 */
export function format(template, values) {
    return Object.entries(values).reduce(
        (text, [key, value]) => text.replace(`:${key}`, String(value)),
        String(template ?? ''),
    );
}
