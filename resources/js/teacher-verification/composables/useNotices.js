import { onBeforeUnmount, ref } from 'vue';

/** How long a toast stays before it fades itself out. */
const LIFETIME = 7000;

/**
 * The page's floating notices. The server's flash messages arrive as the initial
 * list; anything the page then does without a reload (an async upload or a
 * removal) pushes another one. Every toast auto-dismisses, and the timers are
 * cleared on unmount so a navigation never fires into a dead component.
 */
export function useNotices(initial = []) {
    const toasts = ref([]);
    const timers = new Map();
    let nextId = 1;

    function dismiss(id) {
        window.clearTimeout(timers.get(id));
        timers.delete(id);
        toasts.value = toasts.value.filter((toast) => toast.id !== id);
    }

    function push(notice) {
        if (!notice?.message) {
            return;
        }

        const id = nextId++;
        toasts.value.push({
            id,
            tone: notice.tone ?? 'slate',
            title: notice.title ?? null,
            message: notice.message,
            icon: notice.icon ?? null,
        });
        timers.set(id, window.setTimeout(() => dismiss(id), LIFETIME));
    }

    initial.forEach(push);

    onBeforeUnmount(() => {
        timers.forEach((timer) => window.clearTimeout(timer));
        timers.clear();
    });

    return { toasts, push, dismiss };
}

export default useNotices;
