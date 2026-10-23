import { inject } from 'vue';

/**
 * The reviews page is copy-heavy and its cards sit several levels deep, so the
 * app provides Blade's copy block, the payload, the route map and the CSRF token
 * once, and every component injects them instead of prop-drilling strings.
 */
export const COPY_KEY = Symbol('teacher-reviews.copy');
export const URLS_KEY = Symbol('teacher-reviews.urls');
export const PAGE_KEY = Symbol('teacher-reviews.page');
export const CSRF_KEY = Symbol('teacher-reviews.csrf');

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

/**
 * Blade sends countable sentences as "one|many"; pick the right half and fill
 * the count in, so no component builds a plural rule of its own.
 */
export function countLabel(template, count) {
    const parts = String(template ?? '').split('|');

    return format(parts.length > 1 && Number(count) !== 1 ? parts[1] : parts[0], { count });
}
