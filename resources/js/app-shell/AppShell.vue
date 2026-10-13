<script setup>
import { provide } from 'vue';
import { createShellState, SHELL_STATE_KEY } from './composables/useShellState';
import { THEME_PRESET_KEY, useThemePreset } from './composables/useThemePreset';
import AppSidebar from './components/AppSidebar.vue';
import AppTopbar from './components/AppTopbar.vue';

const props = defineProps({
    config: { type: Object, required: true },
    headerHtml: { type: String, default: '' },
});

// The preset ref follows localStorage + the `theme-changed` event the Theme
// Settings screen fires, so the chrome restyles live during a preview.
const preset = useThemePreset(props.config.theme?.preset ?? 'classic');
provide(THEME_PRESET_KEY, preset);

const shell = createShellState();
provide(SHELL_STATE_KEY, shell);

// Destructured so the template auto-unwraps the refs.
const { sidebarOpen, closeSidebar } = shell;
</script>

<template>
    <!-- Preset accent bars -->
    <div
        v-if="preset === 'sunset'"
        class="fixed inset-x-0 top-0 z-50 h-1.5 bg-gradient-to-r from-amber-500 via-rose-500 to-indigo-500"
        aria-hidden="true"
    ></div>
    <div
        v-if="preset === 'ocean'"
        class="fixed inset-x-0 top-0 z-50 h-1.5 bg-gradient-to-r from-cyan-400 via-blue-500 to-indigo-500"
        aria-hidden="true"
    ></div>

    <!-- Mobile scrim -->
    <Transition
        enter-active-class="transition-opacity ease-linear duration-300"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="transition-opacity ease-linear duration-300"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div
            v-if="sidebarOpen"
            class="fixed inset-0 z-30 bg-slate-900/50 backdrop-blur-sm md:hidden"
            aria-hidden="true"
            @click="closeSidebar"
        ></div>
    </Transition>

    <AppSidebar
        :nav-items="config.navItems"
        :user="config.user"
        :dashboard-url="config.urls.dashboard"
    />

    <AppTopbar
        :header-html="headerHtml"
        :notifications="config.notifications"
        :user="config.user"
        :urls="config.urls"
        :csrf-token="config.csrfToken"
    />
</template>
