import { inject } from 'vue';

/**
 * AdminSettingsApp.vue provides Blade's copy block, route map, CSRF token and
 * currency symbol once so the group cards and fields inject them.
 */
export const COPY_KEY = Symbol('admin-settings.copy');
export const URLS_KEY = Symbol('admin-settings.urls');
export const CSRF_KEY = Symbol('admin-settings.csrf');
export const META_KEY = Symbol('admin-settings.meta');

export function useCopy() {
    return inject(COPY_KEY, {});
}

export function useUrls() {
    return inject(URLS_KEY, {});
}

export function useCsrfToken() {
    return inject(CSRF_KEY, '');
}

export function useMeta() {
    return inject(META_KEY, {});
}
