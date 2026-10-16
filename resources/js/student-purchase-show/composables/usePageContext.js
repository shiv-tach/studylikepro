import { inject } from 'vue';

/**
 * The purchase detail page is copy-heavy, so PurchaseShowApp.vue provides
 * Blade's copy block, route map and CSRF token once and every panel injects them
 * instead of prop-drilling strings through each level of components.
 */
export const COPY_KEY = Symbol('student-purchase-show.copy');
export const URLS_KEY = Symbol('student-purchase-show.urls');
export const CSRF_KEY = Symbol('student-purchase-show.csrf');

export function useCopy() {
    return inject(COPY_KEY, {});
}

export function useUrls() {
    return inject(URLS_KEY, {});
}

export function useCsrfToken() {
    return inject(CSRF_KEY, '');
}
