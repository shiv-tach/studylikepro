/**
 * The public catalog's in-page navigation. A subject link asks the server for
 * the island the subject page would have embedded and the app switches to it,
 * so the visitor stays on one document while the URL, the title and the
 * history entry keep up. The browse state (level + grade) is fetched the same
 * way, so the sticky explorer never reloads the page either.
 */

const pageCache = new Map();
const catalogCache = new Map();

export function dataUrlFor(pageUrl) {
    const url = new URL(pageUrl, window.location.origin);
    url.pathname = `${url.pathname.replace(/\/+$/, '')}/data`;

    return url.toString();
}

export async function loadPage(pageUrl) {
    return cached(dataUrlFor(pageUrl), pageCache);
}

/**
 * The catalog state for one level/grade selection.
 *
 * @param {string} baseUrl the catalog page URL (`urls.subjects`)
 * @param {{ level?: string|null, grade?: number|string|null }} selection
 */
export async function loadCatalogState(baseUrl, { level = null, grade = null } = {}, signal = undefined) {
    const url = new URL(baseUrl, window.location.origin);

    if (level) {
        url.searchParams.set('level', level);
    }

    if (grade !== null && grade !== undefined && grade !== '') {
        url.searchParams.set('grade', grade);
    }

    return cached(dataUrlFor(url.toString()), catalogCache, signal);
}

async function cached(url, cache, signal = undefined) {
    if (!cache.has(url)) {
        cache.set(url, fetchIsland(url, signal));
    }

    try {
        const payload = await cache.get(url);

        // A redirect to the student workspace resolves to null; do not let the
        // next attempt reuse it.
        if (payload === null) {
            cache.delete(url);
        }

        return payload;
    } catch (error) {
        cache.delete(url);
        throw error;
    }
}

async function fetchIsland(url, signal = undefined) {
    const response = await fetch(url, {
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin',
        signal,
    });

    // Signed-in students are sent to their workspace page by the server; follow
    // it as a normal navigation instead of rendering the public island.
    if (response.redirected) {
        window.location.assign(response.url);

        return null;
    }

    if (!response.ok) {
        throw new Error(`Could not load ${url}: ${response.status}`);
    }

    return response.json();
}

/** A left-click without a modifier key: the only one the app should hijack. */
export function isPlainClick(event) {
    return event.button === 0 && !event.metaKey && !event.ctrlKey && !event.shiftKey && !event.altKey;
}
