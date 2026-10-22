import { ref, watch } from 'vue';

const STORAGE_KEY = 'teacherOrdersView';
const VIEWS = ['list', 'card'];

function readStoredView() {
    try {
        const stored = window.localStorage.getItem(STORAGE_KEY);

        return VIEWS.includes(stored) ? stored : 'list';
    } catch (error) {
        return 'list';
    }
}

/**
 * The order book opens as a list; the toolbar can switch every order to a card,
 * and the pick survives the next visit so a teacher who prefers cards does not
 * have to switch back on every page load.
 */
export function useViewMode() {
    const view = ref(readStoredView());

    watch(view, (next) => {
        try {
            window.localStorage.setItem(STORAGE_KEY, next);
        } catch (error) {
            // Private mode or blocked storage: the toggle still works per page.
        }
    });

    return view;
}

export default useViewMode;
