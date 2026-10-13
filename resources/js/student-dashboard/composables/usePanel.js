import { computed, inject } from 'vue';
import { panelClass } from '../styles';
import { THEME_PRESET_KEY } from './useThemePreset';

/**
 * A dashboard card surface that follows the shell's selected theme preset.
 */
export function usePanel(interactive = false) {
    const preset = inject(THEME_PRESET_KEY, null);

    return computed(() => panelClass(preset?.value ?? 'classic', interactive));
}

export default usePanel;
