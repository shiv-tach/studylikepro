<script setup>
/**
 * The half-tone panel both subject pages fall back to when a group has nothing
 * to show — no subjects in a basket, no services, no teachers, no lessons.
 */
import { usePanel } from '../composables/usePanel';
import AppIcon from './AppIcon.vue';

defineProps({
    icon: { type: String, default: 'search' },
    message: { type: String, required: true },
    linkLabel: { type: String, default: null },
    linkHref: { type: String, default: null },
});

const panel = usePanel();</script>

<template>
    <div :class="[panel, 'flex flex-col items-center gap-3 text-center']">
        <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-100 p-2.5 text-slate-400 dark:bg-slate-800 dark:text-slate-500">
            <AppIcon :name="icon" />
        </span>

        <p class="max-w-md text-sm text-slate-500 dark:text-slate-400">{{ message }}</p>

        <a
            v-if="linkHref && linkLabel"
            :href="linkHref"
            class="inline-flex items-center gap-1 text-sm font-semibold text-primary transition-colors hover:text-primary/80"
        >
            {{ linkLabel }}
            <span class="h-4 w-4"><AppIcon name="arrow-right" /></span>
        </a>
    </div>
</template>
