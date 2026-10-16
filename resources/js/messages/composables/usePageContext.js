import { inject } from 'vue';

/**
 * The inbox is copy-driven, so App.vue provides its Blade copy block and route
 * map once and every component injects them instead of prop-drilling strings
 * through each level.
 */
export const COPY_KEY = Symbol('messages.copy');
export const URLS_KEY = Symbol('messages.urls');

export function useCopy() {
    return inject(COPY_KEY, {});
}

export function useUrls() {
    return inject(URLS_KEY, {});
}
