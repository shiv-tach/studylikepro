import { inject } from 'vue';

/**
 * The settings home is copy-heavy, so SettingsHomeApp.vue provides Blade's copy
 * block, route map and CSRF token once and every card injects them instead of
 * prop-drilling strings through each card.
 */
export const COPY_KEY = Symbol('settings-home.copy');
export const URLS_KEY = Symbol('settings-home.urls');
export const CSRF_KEY = Symbol('settings-home.csrf');

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
 * Fill a Blade sentence that carries a `:placeholder` the server cannot know —
 * the unread badge count is only known once the notification table is read.
 */
export function format(template, values) {
    return Object.entries(values).reduce(
        (text, [key, value]) => text.replace(`:${key}`, String(value)),
        String(template ?? ''),
    );
}
