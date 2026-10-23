import { inject } from 'vue';

/**
 * The availability editor is copy-heavy and shared by two pages (onboarding
 * step 2 and the manager), so each app provides Blade's copy block, route map
 * and CSRF token once and every card injects them.
 */
export const COPY_KEY = Symbol('availability.copy');
export const URLS_KEY = Symbol('availability.urls');
export const CSRF_KEY = Symbol('availability.csrf');

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
