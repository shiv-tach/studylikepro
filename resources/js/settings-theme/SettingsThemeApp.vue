<script setup>
/**
 * Theme settings at /settings/theme: pick a preset, an accent, a colour mode and
 * a sidebar style, all previewed live. Blade owns the option lists, the saved
 * choice, the copy and the route map; this app only draws the pickers and posts
 * the draft back.
 */
import { provide } from 'vue';
import { THEME_PRESET_KEY, useThemePreset } from '../shared/composables/useThemePreset';
import RevealOnScroll from '../shared/components/RevealOnScroll.vue';
import UiAlert from '../shared/components/UiAlert.vue';
import { COPY_KEY, CSRF_KEY, URLS_KEY } from './composables/usePageContext';
import { THEME_PICKER_KEY, createThemePicker } from './composables/useThemePicker';
import AccentColorPicker from './components/AccentColorPicker.vue';
import ColorModeToggle from './components/ColorModeToggle.vue';
import SidebarStylePicker from './components/SidebarStylePicker.vue';
import ThemePresetGrid from './components/ThemePresetGrid.vue';
import ThemeSaveBar from './components/ThemeSaveBar.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

provide(THEME_PRESET_KEY, useThemePreset(props.config.theme?.preset ?? 'classic'));
provide(COPY_KEY, props.config.copy ?? {});
provide(URLS_KEY, props.config.urls ?? {});
provide(CSRF_KEY, props.config.csrfToken ?? '');

const picker = createThemePicker(props.config.selected ?? {});
provide(THEME_PICKER_KEY, picker);

const { draft, selectPreset, selectAccent, selectMode, selectSidebarStyle } = picker;
</script>

<template>
    <div class="mx-auto max-w-5xl space-y-8">
        <RevealOnScroll v-if="config.status?.updated">
            <UiAlert tone="emerald" icon="check-circle">
                <span class="font-semibold">{{ config.copy.updatedTitle }}</span>
                <span class="block text-xs">{{ config.copy.updatedHint }}</span>
            </UiAlert>
        </RevealOnScroll>

        <RevealOnScroll>
            <ThemePresetGrid
                :presets="config.presets"
                :model-value="draft.preset"
                :title="config.copy.presetsTitle"
                :hint="config.copy.presetsHint"
                @update:model-value="selectPreset"
            />
        </RevealOnScroll>

        <RevealOnScroll :delay="20">
            <AccentColorPicker
                :accents="config.accents"
                :model-value="draft.accent"
                :title="config.copy.accentTitle"
                :hint="config.copy.accentHint"
                @update:model-value="selectAccent"
            />
        </RevealOnScroll>

        <RevealOnScroll :delay="40">
            <ColorModeToggle
                :modes="config.modes"
                :model-value="draft.mode"
                :title="config.copy.modeTitle"
                :hint="config.copy.modeHint"
                @update:model-value="selectMode"
            />
        </RevealOnScroll>

        <RevealOnScroll :delay="60">
            <SidebarStylePicker
                :styles="config.sidebarStyles"
                :model-value="draft.sidebarStyle"
                :title="config.copy.sidebarTitle"
                :hint="config.copy.sidebarHint"
                @update:model-value="selectSidebarStyle"
            />
        </RevealOnScroll>

        <ThemeSaveBar :draft="draft" />
    </div>
</template>
