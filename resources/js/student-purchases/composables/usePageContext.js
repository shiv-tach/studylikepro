import { inject } from 'vue';

/**
 * The purchase list is copy-heavy, so PurchasesApp.vue provides Blade's copy
 * block, route map and CSRF token once and every card injects them instead of
 * prop-drilling strings through each level of components.
 */
export const COPY_KEY = Symbol('student-purchases.copy');
export const URLS_KEY = Symbol('student-purchases.urls');
export const CSRF_KEY = Symbol('student-purchases.csrf');

export function useCopy() {
    return inject(COPY_KEY, {});
}

export function useUrls() {
    return inject(URLS_KEY, {});
}

export function useCsrfToken() {
    return inject(CSRF_KEY, '');
}
