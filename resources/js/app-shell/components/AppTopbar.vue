<script setup>
import { computed } from 'vue';
import { headerClass } from '../theme';
import { useShell } from '../composables/useShell';
import AppIcon from './AppIcon.vue';
import NotificationBell from './NotificationBell.vue';
import ProfileMenu from './ProfileMenu.vue';

const props = defineProps({
    // Server-rendered page heading (the Blade `header` slot), handed to Vue as
    // an HTML string so the top bar owns the whole chrome.
    headerHtml: { type: String, default: '' },
    notifications: { type: Object, default: () => ({ unreadCount: 0, items: [] }) },
    user: { type: Object, required: true },
    urls: { type: Object, required: true },
    csrfToken: { type: String, required: true },
});

const { preset, toggleSidebar } = useShell();

const barClass = computed(() => headerClass(preset.value));
</script>

<template>
    <header
        :class="[barClass, 'sticky top-0 z-20 flex h-16 w-full items-center justify-between gap-4 border-b px-4 backdrop-blur-md sm:px-6 lg:px-8']"
    >
        <div class="flex min-w-0 items-center gap-4">
            <button
                type="button"
                class="rounded-lg p-1.5 text-slate-500 transition-colors hover:bg-slate-100 dark:text-slate-400 dark:hover:bg-slate-800 md:hidden"
                aria-label="Toggle navigation"
                @click="toggleSidebar"
            >
                <span class="block h-6 w-6"><AppIcon name="menu" /></span>
            </button>

            <div v-if="headerHtml" class="flex min-w-0 items-center" v-html="headerHtml"></div>
        </div>

        <div class="flex items-center gap-4">
            <NotificationBell :notifications="notifications" :urls="urls" :csrf-token="csrfToken" />
            <ProfileMenu :user="user" :urls="urls" :csrf-token="csrfToken" />
        </div>
    </header>
</template>
