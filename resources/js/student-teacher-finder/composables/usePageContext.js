import { inject } from 'vue';

/**
 * The finder is copy-heavy: every label, empty-state sentence and count comes
 * from the Blade island, so App.vue provides the copy block and the route map
 * once and each card injects them instead of prop-drilling strings down.
 */
export const COPY_KEY = Symbol('student-teacher-finder.copy');
export const URLS_KEY = Symbol('student-teacher-finder.urls');

export function useCopy() {
    return inject(COPY_KEY, {});
}

export function useUrls() {
    return inject(URLS_KEY, {});
}
