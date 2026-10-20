import { ref } from 'vue';

/**
 * Keyboard support for the explorer's picker rows: arrow keys move between the
 * options (wrapping at the ends), Home/End jump to the first/last one. The
 * options stay normal buttons in the tab order — this only adds the shortcuts
 * a tab-like control is expected to have.
 */
export function useRovingFocus(selector = '[data-focusable]') {
    const container = ref(null);

    function items() {
        return [...(container.value?.querySelectorAll(selector) ?? [])];
    }

    function focusAt(index) {
        const all = items();

        if (!all.length) {
            return;
        }

        all[(index + all.length) % all.length]?.focus();
    }

    function onKeydown(event) {
        const all = items();

        if (!all.length) {
            return;
        }

        const index = all.indexOf(document.activeElement);

        switch (event.key) {
            case 'ArrowRight':
                event.preventDefault();
                focusAt(index + 1);
                break;
            case 'ArrowLeft':
                event.preventDefault();
                focusAt(index - 1);
                break;
            case 'Home':
                event.preventDefault();
                focusAt(0);
                break;
            case 'End':
                event.preventDefault();
                focusAt(all.length - 1);
                break;
        }
    }

    return { container, onKeydown, focusAt };
}
