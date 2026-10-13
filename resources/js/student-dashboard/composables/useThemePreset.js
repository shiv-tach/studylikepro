import { onBeforeUnmount, onMounted, ref } from 'vue';

export const THEME_PRESET_KEY = Symbol('student-dashboard.theme-preset');

const STORAGE_KEY = 'themePreset';
const KNOWN_PRESETS = ['classic', 'forest', 'midnight', 'sunset', 'glass', 'ocean'];

/**
 * The preset lives in localStorage and the Alpine shell announces every change
 * with a `theme-changed` window event, so the Vue panels restyle themselves the
 * moment the user picks a new preset in Theme Settings.
 */
export function useThemePreset(fallback = 'classic') {
    const stored = window.localStorage.getItem(STORAGE_KEY);
    const preset = ref(KNOWN_PRESETS.includes(stored) ? stored : fallback);

    const sync = (event) => {
        const next = event?.detail?.preset ?? window.localStorage.getItem(STORAGE_KEY);

        if (KNOWN_PRESETS.includes(next)) {
            preset.value = next;
        }
    };

    onMounted(() => window.addEventListener('theme-changed', sync));
    onBeforeUnmount(() => window.removeEventListener('theme-changed', sync));

    return preset;
}

export default useThemePreset;
