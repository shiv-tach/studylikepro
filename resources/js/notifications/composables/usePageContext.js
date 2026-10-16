import { inject } from 'vue';

/**
 * NotificationsApp.vue provides Blade's copy block, route map and CSRF token
 * once; the banner and each row inject them.
 */
export const COPY_KEY = Symbol('notifications.copy');
export const URLS_KEY = Symbol('notifications.urls');
export const CSRF_KEY = Symbol('notifications.csrf');

export function useCopy() {
    return inject(COPY_KEY, {});
}

export function useUrls() {
    return inject(URLS_KEY, {});
}

export function useCsrfToken() {
    return inject(CSRF_KEY, '');
}
