import { nextTick, onBeforeUnmount, onMounted, ref } from 'vue';

/** How often the thread asks for what was posted after its cursor. */
const POLL_INTERVAL = 5000;

/**
 * The live side of a lesson thread: the messages on screen, the draft in the
 * composer, the send call and the five-second poll that catches the other side's
 * replies.
 *
 * Blade hands over the first page in exactly the payload shape the store and poll
 * endpoints return, so a message appended by the poller is indistinguishable from
 * one rendered by the server — there is no second template to keep in step.
 */
export function createThread({
    initialMessages = [],
    pollUrl = '',
    storeUrl = '',
    csrfToken = '',
    labels = {},
}) {
    const messages = ref(initialMessages.map((message) => ({ ...message })));
    const body = ref('');
    const sending = ref(false);
    const errors = ref('');
    // The scroll container ThreadMessages.vue binds with `ref="threadEl"`.
    const threadEl = ref(null);

    // The cursor the poller sends and the ids already on screen, so a message
    // that arrives by poll while a send is in flight never renders twice.
    const after = ref(messages.value.reduce((max, message) => Math.max(max, message.id), 0));
    const rendered = new Set(messages.value.map((message) => message.id));

    let timer = null;

    function scrollToEnd() {
        nextTick(() => {
            const element = threadEl.value;

            if (element) {
                element.scrollTop = element.scrollHeight;
            }
        });
    }

    function append(message) {
        if (!message || rendered.has(message.id)) {
            return;
        }

        rendered.add(message.id);
        messages.value.push(message);
        after.value = Math.max(after.value, message.id);
        scrollToEnd();
    }

    /**
     * Post the draft and, optionally, the photo the composer picked.
     * Returns false when nothing went out, so the composer can keep the file.
     */
    async function send(file = null) {
        if (sending.value) {
            return false;
        }

        const text = body.value.trim();

        if (!text && !file) {
            errors.value = labels.writeFirst;

            return false;
        }

        const form = new FormData();

        if (text) {
            form.append('body', text);
        }

        if (file) {
            form.append('image', file);
        }

        sending.value = true;
        errors.value = '';

        try {
            const response = await fetch(storeUrl, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, Accept: 'application/json' },
                body: form,
            });

            if (response.status === 422) {
                const data = await response.json();
                errors.value = Object.values(data.errors ?? {}).flat().join(' ');

                return false;
            }

            if (!response.ok) {
                errors.value = labels.failed;

                return false;
            }

            const data = await response.json();
            append(data.message);
            body.value = '';

            return true;
        } catch (error) {
            errors.value = labels.offline;

            return false;
        } finally {
            sending.value = false;
            scrollToEnd();
        }
    }

    async function tick() {
        // A hidden tab has nothing to keep up with; the next visible tick catches up.
        if (document.hidden) {
            return;
        }

        try {
            const response = await fetch(`${pollUrl}?after=${after.value}`, {
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            (data.messages ?? []).forEach(append);
        } catch (error) {
            // Offline for a moment; the next tick catches up.
        }
    }

    onMounted(() => {
        scrollToEnd();
        timer = window.setInterval(tick, POLL_INTERVAL);
    });

    onBeforeUnmount(() => {
        if (timer) {
            window.clearInterval(timer);
        }
    });

    return { messages, body, sending, errors, threadEl, scrollToEnd, send };
}

export default createThread;
