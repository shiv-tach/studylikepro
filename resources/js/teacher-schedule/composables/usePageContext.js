import { inject } from 'vue';

/**
 * The schedule is copy-heavy and its rows sit several levels deep, so the app
 * provides Blade's copy block, the page payload and the route map once and
 * every component injects them instead of prop-drilling strings.
 */
export const COPY_KEY = Symbol('teacher-schedule.copy');
export const URLS_KEY = Symbol('teacher-schedule.urls');
export const PAGE_KEY = Symbol('teacher-schedule.page');

export function useCopy() {
    return inject(COPY_KEY, {});
}

export function useUrls() {
    return inject(URLS_KEY, {});
}

export function usePage() {
    return inject(PAGE_KEY, {});
}

/**
 * Fill a Blade sentence that carries a `:placeholder` the server cannot know.
 */
export function format(template, values) {
    return Object.entries(values).reduce(
        (text, [key, value]) => text.replace(`:${key}`, String(value)),
        String(template ?? ''),
    );
}

/**
 * Blade sends countable sentences as "one|many"; pick the right half and fill
 * the count in, so no component has to build a plural rule of its own.
 */
export function countLabel(template, count) {
    const parts = String(template ?? '').split('|');

    return format(parts.length > 1 && Number(count) !== 1 ? parts[1] : parts[0], { count });
}
