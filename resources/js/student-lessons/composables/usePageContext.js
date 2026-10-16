import { inject } from 'vue';

/**
 * The lesson list is copy-heavy, so App.vue provides its Blade copy block and
 * route map once and every card injects them instead of prop-drilling strings
 * through each level of components.
 */
export const COPY_KEY = Symbol('student-lessons.copy');
export const URLS_KEY = Symbol('student-lessons.urls');

export function useCopy() {
    return inject(COPY_KEY, {});
}

export function useUrls() {
    return inject(URLS_KEY, {});
}
