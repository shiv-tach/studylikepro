import { inject } from 'vue';

/**
 * The earnings dashboard is copy-heavy and its rows sit several levels deep, so
 * the app provides Blade's copy block, the whole payload, the route map and the
 * money format once, and every component injects them instead of prop-drilling
 * strings.
 */
export const COPY_KEY = Symbol('teacher-earnings.copy');
export const URLS_KEY = Symbol('teacher-earnings.urls');
export const PAGE_KEY = Symbol('teacher-earnings.page');

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
 * Fill a Blade sentence that carries `:placeholders` the server cannot know yet.
 */
export function format(template, values = {}) {
    return Object.entries(values).reduce(
        (text, [key, value]) => text.replaceAll(`:${key}`, String(value ?? '')),
        String(template ?? ''),
    );
}

/**
 * Blade sends countable sentences as "one|many"; pick the right half, fill the
 * count in, and fill any other placeholders the sentence carries, so no
 * component builds a plural rule of its own.
 */
export function countLabel(template, count, values = {}) {
    const parts = String(template ?? '').split('|');
    const sentence = parts.length > 1 && Number(count) !== 1 ? parts[1] : parts[0];

    return format(sentence, { count, ...values });
}
