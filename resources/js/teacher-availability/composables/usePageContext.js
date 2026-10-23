import { inject } from 'vue';

/**
 * The availability manager is copy-heavy and its panels each own a real form,
 * so App.vue provides Blade's copy block, the route map, the CSRF token and the
 * whole island once, and the components inject them.
 */
export const COPY_KEY = Symbol('teacher-availability.copy');
export const URLS_KEY = Symbol('teacher-availability.urls');
export const CSRF_KEY = Symbol('teacher-availability.csrf');
export const PAGE_KEY = Symbol('teacher-availability.page');

export function useCopy() {
    return inject(COPY_KEY, {});
}

export function useUrls() {
    return inject(URLS_KEY, {});
}

export function useCsrfToken() {
    return inject(CSRF_KEY, '');
}

export function usePage() {
    return inject(PAGE_KEY, {});
}

/**
 * Fill a Blade sentence that carries a `:placeholder` the server cannot know —
 * weekly hours, the platform fee and the date range inside a dialog.
 */
export function format(template, values) {
    return Object.entries(values).reduce(
        (text, [key, value]) => text.replace(`:${key}`, String(value)),
        String(template ?? ''),
    );
}

/**
 * Mirror `PlatformSettings::formatMinor()` for the one figure the teacher types
 * live (their earnings preview): the island carries the platform symbol, so the
 * client never guesses a currency.
 */
export function formatMinor(minor, currency) {
    const amount = (Number(minor) || 0) / 100;
    const symbol = currency?.symbol ?? '';

    return `${symbol} ${amount.toLocaleString('en-US', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })}`.trim();
}
