import { onBeforeUnmount, onMounted } from 'vue';

/**
 * Close a popover when the pointer lands outside it or the user presses Escape.
 * Both listeners are torn down with the component, so dropdowns can be mounted
 * and unmounted freely without leaking document handlers.
 */
export function useClickOutside(elementRef, close) {
    const onPointerDown = (event) => {
        const element = elementRef.value;

        if (element && !element.contains(event.target)) {
            close();
        }
    };

    const onKeydown = (event) => {
        if (event.key === 'Escape') {
            close();
        }
    };

    onMounted(() => {
        document.addEventListener('pointerdown', onPointerDown, true);
        document.addEventListener('keydown', onKeydown);
    });

    onBeforeUnmount(() => {
        document.removeEventListener('pointerdown', onPointerDown, true);
        document.removeEventListener('keydown', onKeydown);
    });
}

export default useClickOutside;
