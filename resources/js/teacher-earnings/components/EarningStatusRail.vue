<script setup>
/**
 * The ledger's status filter. Every tab is a real link, so the state lives in
 * the URL and works with JavaScript off; the counts come from Blade's grouped
 * query over the whole ledger, never from the current page.
 */
import { useCopy, usePage } from '../composables/usePageContext';

const copy = useCopy();
const page = usePage();
</script>

<template>
    <nav :aria-label="copy.ledger?.filtersLabel" class="flex flex-wrap gap-2">
        <a
            v-for="tab in page.statusTabs"
            :key="tab.key || 'all'"
            :href="tab.url"
            :aria-current="tab.active ? 'page' : undefined"
            class="inline-flex items-center gap-2 rounded-xl border px-3 py-2 text-sm font-semibold transition-colors"
            :class="tab.active
                ? 'border-primary bg-primary/10 text-primary'
                : 'border-slate-200 bg-white text-slate-600 hover:border-primary/40 hover:text-primary dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300'"
        >
            {{ tab.label }}
            <span
                class="rounded-full px-1.5 py-0.5 text-[11px] font-bold tabular-nums"
                :class="tab.active
                    ? 'bg-primary/15 text-primary'
                    : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'"
            >
                {{ tab.count }}
            </span>
        </a>
    </nav>
</template>
