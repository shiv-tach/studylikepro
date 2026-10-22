import { inject } from 'vue';

/**
 * One order is a set of independent panels, so the app provides Blade's copy
 * block, the order payload and the route map once and each panel injects what it
 * renders instead of carrying props through two levels.
 */
export const COPY_KEY = Symbol('teacher-order-show.copy');
export const URLS_KEY = Symbol('teacher-order-show.urls');
export const PAGE_KEY = Symbol('teacher-order-show.page');

export function useCopy() {
    return inject(COPY_KEY, {});
}

export function useUrls() {
    return inject(URLS_KEY, {});
}

export function usePage() {
    return inject(PAGE_KEY, {});
}
