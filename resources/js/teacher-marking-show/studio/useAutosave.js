import { onBeforeUnmount, ref } from 'vue';

/**
 * Debounced autosave for the studio. The endpoint accepts a partial payload,
 * but this client always sends the current markup, rubric scores and feedback
 * so a retry after a dropped connection restores the whole state.
 *
 * Status is a small state machine the top bar renders: idle → saving → saved,
 * or error (which schedules nothing automatically; the next edit retries).
 */
export function createAutosave({ url, token, snapshot, onSaved, debounce = 1200 }) {
    const status = ref('idle');
    const savedAt = ref(null);
    const error = ref(null);

    let timer = null;
    let inflight = null;

    function schedule(delay = debounce) {
        if (!url) {
            return;
        }

        clearTimeout(timer);
        timer = setTimeout(flush, delay);
    }

    async function flush() {
        if (!url) {
            return;
        }

        clearTimeout(timer);

        if (inflight) {
            return inflight;
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
                    body: JSON.stringify(snapshot()),
                });

                if (!response.ok) {
                    const payload = await response.json().catch(() => null);
                    const message = payload?.message ?? `Autosave failed (${response.status}).`;

                    throw new Error(message);
                }

                const data = await response.json();

                savedAt.value = data.savedAt ?? null;
                status.value = 'saved';
                onSaved?.(data);

                return data;
            } catch (exception) {
                status.value = 'error';
                error.value = exception?.message ?? 'Autosave failed.';

                return null;
            } finally {
                inflight = null;
            }
        })();

        return inflight;
    }

    function cancel() {
        clearTimeout(timer);
    }

    onBeforeUnmount(cancel);

    return { status, savedAt, error, schedule, flush, cancel };
}
