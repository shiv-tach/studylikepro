<script setup>
/**
 * The Upcoming / Past switch. Every tab is a server URL the island carries with
 * the current filters kept, so switching never loses a filter and paging stays
 * a plain page load.
 */
import { useCopy } from '../composables/usePageContext';

defineProps({
    tabs: { type: Array, default: () => [] },
    active: { type: String, required: true },
});

const copy = useCopy();
</script>

<template>
    <nav v-if="tabs.length" class="flex flex-wrap gap-2" :aria-label="copy.tabsLabel">
        <a
            v-for="tab in tabs"
            :key="tab.key"
            :href="tab.url"
            :aria-current="tab.key === active ? 'page' : undefined"
            class="rounded-xl px-4 py-2 text-sm font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
            :class="tab.key === active
                ? 'bg-primary text-white shadow-sm'
                : 'border border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-800 dark:text-slate-300 dark:hover:bg-slate-800'"
        >
            {{ tab.label }}
        </a>
    </nav>
</template>
