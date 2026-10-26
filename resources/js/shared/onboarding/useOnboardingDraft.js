import { onBeforeUnmount, ref } from 'vue';

/**
 * Autosave for one onboarding step.
 *
 * Every edit is written to `localStorage` immediately (so even a crashed tab is
 * recoverable) and pushed to the server on a short debounce. The browser also
 * flushes on tab hide and on page hide, and warns before leaving while a save
 * is still pending. The server copy is what restores the step in another
 * browser; the local copy covers a failed or interrupted request.
 */
export function createOnboardingDraft({
    url = null,
    deleteUrl = null,
    token = '',
    step,
    snapshot,
    initialSavedAt = null,
    storageKey = null,
    debounce = 1000,
} = {}) {
    const status = ref(initialSavedAt ? 'saved' : 'idle');
    const savedAt = ref(initialSavedAt);
    const error = ref(null);
    const online = ref(typeof navigator === 'undefined' ? true : navigator.onLine);

    const key = storageKey ?? `studylikepro:onboarding-draft:${step}`;

    let timer = null;
    let inflight = null;

    function readLocal() {
        try {
            const raw = window.localStorage.getItem(key);

            if (!raw) {
                return null;
            }

            const parsed = JSON.parse(raw);

            return parsed && typeof parsed === 'object' ? parsed : null;
        } catch (exception) {
            return null;
        }
    }

    function writeLocal(data) {
        try {
            window.localStorage.setItem(key, JSON.stringify({ data, savedAt: new Date().toISOString() }));
        } catch (exception) {
            // Private mode or a full quota: the server draft is still the source
            // of truth, so this is not worth interrupting the teacher for.
        }
    }

    function clearLocal() {
        try {
            window.localStorage.removeItem(key);
        } catch (exception) {
            // Ignored for the same reason as above.
        }
    }

    function schedule(delay = debounce) {
        if (!url) {
            return;
        }

        const data = snapshot();
        writeLocal(data);

        if (!online.value) {
            status.value = 'offline';

            return;
        }

        status.value = 'dirty';
        error.value = null;

        clearTimeout(timer);
        timer = setTimeout(flush, delay);
    }

    async function flush() {
        if (!url) {
            return null;
        }

        clearTimeout(timer);

        if (inflight) {
            return inflight;
        }

        if (!online.value) {
            status.value = 'offline';

            return null;
        }

        status.value = 'saving';
        error.value = null;

        inflight = (async () => {
            try {
                const response = await fetch(url, {
                    method: 'PATCH',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ step, data: snapshot() }),
                });

                if (!response.ok) {
                    const payload = await response.json().catch(() => null);

                    throw new Error(payload?.message ?? `Draft save failed (${response.status}).`);
                }

                const payload = await response.json();

                savedAt.value = payload.savedAt ?? new Date().toISOString();
                status.value = 'saved';

                return payload;
            } catch (exception) {
                status.value = online.value ? 'error' : 'offline';
                error.value = exception?.message ?? 'Draft save failed.';

                return null;
            } finally {
                inflight = null;
            }
        })();

        return inflight;
    }

    /**
     * Drop the draft (server and this device) and reload so the stored answers
     * come back. Reloading is deliberate: it re-runs every Blade merge instead
     * of trying to reverse each field in JavaScript.
     */
    async function discard() {
        clearLocal();

        if (deleteUrl) {
            try {
                await fetch(`${deleteUrl}?step=${encodeURIComponent(step)}`, {
                    method: 'DELETE',
                    credentials: 'same-origin',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
            } catch (exception) {
                // The reload below still shows the stored answers.
            }
        }

        window.location.reload();
    }

    function onBeforeUnload(event) {
        if (status.value === 'dirty' || status.value === 'saving') {
            event.preventDefault();
            event.returnValue = '';
        }
    }

    function onVisibilityChange() {
        if (document.visibilityState === 'hidden') {
            flush();
        }
    }

    function onPageHide() {
        flush();
    }

    function onOnline() {
        online.value = true;

        if (status.value === 'offline') {
            schedule(0);
        }
    }

    function onOffline() {
        online.value = false;
        status.value = 'offline';
    }

    if (typeof window !== 'undefined') {
        window.addEventListener('beforeunload', onBeforeUnload);
        document.addEventListener('visibilitychange', onVisibilityChange);
        window.addEventListener('pagehide', onPageHide);
        window.addEventListener('online', onOnline);
        window.addEventListener('offline', onOffline);
    }

    onBeforeUnmount(() => {
        clearTimeout(timer);

        if (typeof window !== 'undefined') {
            window.removeEventListener('beforeunload', onBeforeUnload);
            document.removeEventListener('visibilitychange', onVisibilityChange);
            window.removeEventListener('pagehide', onPageHide);
            window.removeEventListener('online', onOnline);
            window.removeEventListener('offline', onOffline);
        }
    });

    return {
        status,
        savedAt,
        error,
        online,
        schedule,
        flush,
        discard,
        readLocal,
        clearLocal,
    };
}

/**
 * The local snapshot is only worth restoring when it is newer than the server
 * draft the page was rendered from.
 */
export function localDraftIsNewer(local, serverSavedAt) {
    if (!local?.savedAt) {
        return false;
    }

    if (!serverSavedAt) {
        return true;
    }

    return new Date(local.savedAt).getTime() > new Date(serverSavedAt).getTime();
}
