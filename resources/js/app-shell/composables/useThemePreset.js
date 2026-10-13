import { onBeforeUnmount, onMounted, ref } from 'vue';
import { PRESETS } from '../theme';

export const THEME_PRESET_KEY = Symbol('app-shell.theme-preset');

const STORAGE_KEY = 'themePreset';

/**
 * The preset is written to `localStorage` by the head bootstrap and by the
 * Theme Settings screen, which then announces every change with a
 * `theme-changed` window event. The shell mirrors that so the sidebar and top
 * bar restyle themselves live while the user previews a preset.
 */
export function useThemePreset(fallback = 'classic') {
    const stored = window.localStorage.getItem(STORAGE_KEY);
    const preset = ref(PRESETS.includes(stored) ? stored : fallback);

    const sync = (event) => {
        const next = event?.detail?.preset ?? window.localStorage.getItem(STORAGE_KEY);

        if (PRESETS.includes(next) && next !== preset.value) {
            preset.value = next;
        }
    };

    onMounted(() => window.addEventListener('theme-changed', sync));
    onBeforeUnmount(() => window.removeEventListener('theme-changed', sync));

    return preset;
}

export default useThemePreset;
