import { inject } from 'vue';

/**
 * The services page is copy-heavy and its cards sit several levels deep, so
 * TeacherServicesApp.vue provides Blade's copy block, the route map, the CSRF
 * token, the static page data and the live workspace once, and every component
 * injects them instead of prop-drilling strings through each level.
 */
export const COPY_KEY = Symbol('teacher-services.copy');
export const URLS_KEY = Symbol('teacher-services.urls');
export const CSRF_KEY = Symbol('teacher-services.csrf');
export const PAGE_KEY = Symbol('teacher-services.page');
export const WORKSPACE_KEY = Symbol('teacher-services.workspace');

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

export function useWorkspace() {
    return inject(WORKSPACE_KEY, null);
}

/**
 * Fill a Blade sentence that carries a `:placeholder` the server cannot know —
 * a live count or a selected type label.
 */
export function format(template, values) {
    return Object.entries(values).reduce(
        (text, [key, value]) => text.replace(`:${key}`, String(value)),
        String(template ?? ''),
    );
}

/** Pick the singular copy variant at exactly one, the plural one otherwise. */
export function plural(count, one, many) {
    return Number(count) === 1 ? one : many;
}
