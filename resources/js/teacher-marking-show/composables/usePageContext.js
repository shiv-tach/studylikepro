import { inject } from 'vue';

/**
 * The order console is copy-heavy and its cards sit several levels deep, so the
 * app provides Blade's copy block, the payload, the route map and the CSRF token
 * once, and every component injects them instead of prop-drilling strings.
 */
export const COPY_KEY = Symbol('teacher-marking-show.copy');
export const URLS_KEY = Symbol('teacher-marking-show.urls');
export const PAGE_KEY = Symbol('teacher-marking-show.page');
export const CSRF_KEY = Symbol('teacher-marking-show.csrf');

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
