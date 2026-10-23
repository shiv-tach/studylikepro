<script setup>
/**
 * The panel a tab falls back to when it has nothing to show. The icon and copy
 * differ per tab, and the upcoming tab offers the availability CTA because that
 * is the only thing a teacher can do about an empty week.
 */
import AppIcon from '../../shared/components/AppIcon.vue';
import { usePanel } from '../../shared/composables/usePanel';

defineProps({
    icon: { type: String, default: 'calendar' },
    title: { type: String, required: true },
    body: { type: String, default: null },
    linkLabel: { type: String, default: null },
    linkHref: { type: String, default: null },
});

const panel = usePanel();
</script>

<template>
    <div :class="[panel, 'flex flex-col items-center gap-3 text-center']">
        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 p-3 text-primary">
            <AppIcon :name="icon" />
        </span>

        <p class="text-base font-bold text-slate-800 dark:text-slate-100">{{ title }}</p>
        <p v-if="body" class="max-w-md text-sm leading-relaxed text-slate-500 dark:text-slate-400">{{ body }}</p>

        <a
            v-if="linkHref && linkLabel"
            :href="linkHref"
            class="mt-1 inline-flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary/90"
        >
            {{ linkLabel }}
            <span class="h-4 w-4" aria-hidden="true"><AppIcon name="arrow-right" /></span>
        </a>
    </div>
</template>
