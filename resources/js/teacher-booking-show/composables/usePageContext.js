import { inject } from 'vue';

/**
 * The lesson page is copy-heavy and its panels sit several levels deep, so the
 * app provides Blade's copy block, the route map and the CSRF token once and
 * every component injects them instead of prop-drilling strings.
 */
export const COPY_KEY = Symbol('teacher-booking-show.copy');
export const URLS_KEY = Symbol('teacher-booking-show.urls');
export const PAGE_KEY = Symbol('teacher-booking-show.page');
export const CSRF_KEY = Symbol('teacher-booking-show.csrf');

export function useCopy() {
    return inject(COPY_KEY, {});
}

export function useUrls() {
    return inject(URLS_KEY, {});
}

export function usePage() {
    return inject(PAGE_KEY, {});
}

export function useCsrfToken() {
    return inject(CSRF_KEY, '');
}

/**
 * Fill a Blade sentence that carries a `:placeholder` the server cannot know.
 */
export function format(template, values) {
    return Object.entries(values).reduce(
        (text, [key, value]) => text.replace(`:${key}`, String(value)),
        String(template ?? ''),
    );
}
