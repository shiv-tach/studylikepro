import { inject } from 'vue';

/**
 * A lesson thread is copy-driven and lives across several components, so App.vue
 * provides its Blade copy block, route map, CSRF token and the thread state once
 * and every component injects them instead of prop-drilling strings and refs
 * through each level.
 */
export const COPY_KEY = Symbol('messages-thread.copy');
export const URLS_KEY = Symbol('messages-thread.urls');
export const CSRF_KEY = Symbol('messages-thread.csrf');
export const THREAD_KEY = Symbol('messages-thread.thread');

export function useCopy() {
    return inject(COPY_KEY, {});
}

export function useUrls() {
    return inject(URLS_KEY, {});
}

export function useCsrfToken() {
    return inject(CSRF_KEY, '');
}

/**
 * The live thread: the message list, the composer draft and the poller.
 */
export function useThread() {
    return inject(THREAD_KEY, null);
}
