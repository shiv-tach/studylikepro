import { inject } from 'vue';

/**
 * The availability step is copy-heavy, so App.vue provides Blade's copy block,
 * route map and CSRF token once and every card injects them.
 */
export const COPY_KEY = Symbol('teacher-availability-setup.copy');
export const URLS_KEY = Symbol('teacher-availability-setup.urls');
export const CSRF_KEY = Symbol('teacher-availability-setup.csrf');

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
 * the step counter, the weekly hours and the per-day toggle label.
 */
export function format(template, values) {
    return Object.entries(values).reduce(
        (text, [key, value]) => text.replace(`:${key}`, String(value)),
        String(template ?? ''),
    );
}
