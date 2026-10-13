import { ref } from 'vue';

export const SHELL_STATE_KEY = Symbol('app-shell.state');

/**
 * The one piece of shell state the sidebar and the top bar have to agree on:
 * whether the mobile drawer is open. `AppShell.vue` creates it and provides it
 * to every descendant, so the hamburger in the top bar drives the drawer
 * without prop drilling.
 */
export function createShellState() {
    const sidebarOpen = ref(false);

    const openSidebar = () => {
        sidebarOpen.value = true;
    };

    const closeSidebar = () => {
        sidebarOpen.value = false;
    };

    const toggleSidebar = () => {
        sidebarOpen.value = !sidebarOpen.value;
    };

    return {
        sidebarOpen,
        openSidebar,
        closeSidebar,
        toggleSidebar,
    };
}

export default createShellState;
