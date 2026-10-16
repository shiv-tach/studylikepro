import { reactive } from 'vue';

/**
 * The draft theme the user is previewing. Choosing anything applies it live —
 * the head/Alpine theme listener in layouts/app.blade.php listens for the
 * `theme-changed` window event and mirrors localStorage — and the save bar then
 * posts the same four values to the server. `provide`d under THEME_PICKER_KEY so
 * every picker injects one shared draft instead of prop-drilling it.
 */
export const THEME_PICKER_KEY = Symbol('settings-theme.picker');

const STORAGE_KEYS = {
    preset: 'themePreset',
    accent: 'themeAccent',
    mode: 'themeMode',
    sidebarStyle: 'sidebarStyle',
};

export function createThemePicker(initial = {}) {
    const draft = reactive({
        preset: initial.preset ?? 'classic',
        accent: initial.accent ?? 'indigo',
        mode: initial.mode ?? 'light',
        sidebarStyle: initial.sidebarStyle ?? 'flat',
    });

    // Push the whole draft to the shell (live preview) and to localStorage so the
    // choice survives a page navigation, exactly like the Alpine version did.
    function apply() {
        const detail = { ...draft };

        window.dispatchEvent(new CustomEvent('theme-changed', { detail }));

        Object.entries(STORAGE_KEYS).forEach(([key, storageKey]) => {
            window.localStorage.setItem(storageKey, detail[key]);
        });
    }

    function select(key, value) {
        if (draft[key] === value) {
            return;
        }

        draft[key] = value;
        apply();
    }

    return {
        draft,
        selectPreset: (id) => select('preset', id),
        selectAccent: (id) => select('accent', id),
        selectMode: (id) => select('mode', id),
        selectSidebarStyle: (id) => select('sidebarStyle', id),
    };
}

/**
 * Blade sends the labels with `:placeholder`s the server cannot fill — the save
 * bar reads back whatever the user is previewing.
 */
export function format(template, values) {
    return Object.entries(values).reduce(
        (text, [key, value]) => text.replace(`:${key}`, String(value)),
        String(template ?? ''),
    );
}

export default createThemePicker;
