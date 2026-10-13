<script setup>
import { onMounted, ref } from 'vue';
import AppIcon from './AppIcon.vue';

const isDark = ref(false);

onMounted(() => {
    isDark.value = document.documentElement.classList.contains('dark');
});

function toggle() {
    isDark.value = !isDark.value;
    document.documentElement.classList.toggle('dark', isDark.value);

    try {
        // Public pages only load dark when "dark" was picked explicitly — see
        // the theme bootstrap script in the Blade layouts.
        localStorage.setItem('themeMode', isDark.value ? 'dark' : 'light');
    } catch (error) {
        // Storage can be unavailable (private mode); the toggle still works for this page view.
    }
}
</script>

<template>
    <button
        type="button"
        :aria-pressed="isDark"
        :aria-label="isDark ? 'Switch to light theme' : 'Switch to dark theme'"
        :title="isDark ? 'Light theme' : 'Dark theme'"
        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-200/80 bg-white/70 text-slate-500 transition-colors hover:border-primary/40 hover:text-primary dark:border-slate-800/80 dark:bg-slate-900/70 dark:text-slate-400 dark:hover:text-primary"
        @click="toggle"
    >
        <AppIcon :name="isDark ? 'sun' : 'moon'" class="h-5 w-5" />
    </button>
</template>
