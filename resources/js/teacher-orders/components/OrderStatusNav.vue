<script setup>
/**
 * The status rail: All orders plus one link per purchase status, each carrying
 * the count across the teacher's whole book — not just this page. They are real
 * links, so the browser owns navigation and a new tab, Back and the history
 * stack all behave normally.
 */
defineProps({
    tabs: { type: Array, default: () => [] },
    label: { type: String, default: 'Order status' },
});
</script>

<template>
    <nav
        :aria-label="label"
        class="inline-flex max-w-full flex-wrap gap-1 rounded-2xl border border-slate-200/80 bg-white p-1.5 shadow-sm dark:border-slate-800/80 dark:bg-slate-900"
    >
        <a
            v-for="tab in tabs"
            :key="tab.key || 'all'"
            :href="tab.url"
            :aria-current="tab.active ? 'page' : undefined"
            class="inline-flex shrink-0 items-center justify-center gap-2 rounded-xl px-3.5 py-2 text-sm font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
            :class="tab.active
                ? 'bg-primary text-white shadow-sm shadow-primary/30'
                : 'text-slate-600 hover:bg-slate-100 dark:text-slate-300 dark:hover:bg-slate-800'"
        >
            <span class="whitespace-nowrap">{{ tab.label }}</span>

            <span
                class="rounded-full px-2 py-0.5 text-[11px] font-bold tabular-nums"
                :class="tab.active
                    ? 'bg-white/20 text-white'
                    : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'"
            >
                {{ tab.count }}
            </span>
        </a>
    </nav>
</template>
