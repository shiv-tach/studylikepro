import { inject } from 'vue';
import { SHELL_STATE_KEY } from './useShellState';
import { THEME_PRESET_KEY } from './useThemePreset';

/**
 * Read the shell context provided by `AppShell.vue`: the live theme-preset ref
 * and the shared drawer state. Throws early if a component is rendered outside
 * the shell, so a wiring mistake surfaces immediately instead of as a blank
 * panel.
 */
export function useShell() {
    const preset = inject(THEME_PRESET_KEY, null);
    const state = inject(SHELL_STATE_KEY, null);

    if (!preset || !state) {
        throw new Error('useShell() must be called inside <AppShell>.');
    }

    return {
        preset,
        sidebarOpen: state.sidebarOpen,
        openSidebar: state.openSidebar,
        closeSidebar: state.closeSidebar,
        toggleSidebar: state.toggleSidebar,
    };
}

export default useShell;
