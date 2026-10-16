<script setup>
/**
 * The filter bar: the Active / Past switch and the service-type chips. Both are
 * real links the island carries with every other filter already applied, so the
 * query string Laravel validates is built by the browser and paging stays a
 * plain page load.
 */
import { usePanel } from '../../shared/composables/usePanel';
import AppIcon from '../../shared/components/AppIcon.vue';
import { useCopy } from '../composables/usePageContext';

defineProps({
    tabs: { type: Array, default: () => [] },
    activeTab: { type: String, required: true },
    typeOptions: { type: Array, default: () => [] },
    activeType: { type: String, default: '' },
});

const copy = useCopy();
const panel = usePanel();

const tabClass = (isActive) => [
    'rounded-xl px-4 py-2 text-sm font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40',
    isActive
        ? 'bg-primary text-white shadow-sm shadow-primary/20'
        : 'border border-slate-200 text-slate-600 hover:bg-slate-50 dark:border-slate-800 dark:text-slate-300 dark:hover:bg-slate-800',
];

const typeClass = (isActive) => [
    'inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40',
    isActive
        ? 'border-primary/30 bg-primary/10 text-primary'
        : 'border-slate-200/80 text-slate-600 hover:border-primary/40 hover:text-primary dark:border-slate-800/80 dark:text-slate-300',
];
</script>

<template>
    <section :class="panel" :aria-label="copy.filtersLabel">
        <nav v-if="tabs.length" class="flex flex-wrap gap-2" :aria-label="copy.tabsLabel">
            <a
                v-for="tab in tabs"
                :key="tab.key"
                :href="tab.url"
                :aria-current="tab.key === activeTab ? 'page' : undefined"
                :class="tabClass(tab.key === activeTab)"
            >
                {{ tab.label }}
            </a>
        </nav>

        <div v-if="typeOptions.length" class="mt-4 border-t border-slate-200/80 pt-4 dark:border-slate-800/80">
            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">{{ copy.serviceLabel }}</p>

            <div class="mt-2 flex flex-wrap gap-2">
                <a
                    v-for="option in typeOptions"
                    :key="option.value || 'all'"
                    :href="option.url"
                    :aria-current="option.value === activeType ? 'page' : undefined"
                    :class="typeClass(option.value === activeType)"
                >
                    <span v-if="option.icon" class="h-3.5 w-3.5 shrink-0"><AppIcon :name="option.icon" /></span>
                    {{ option.label }}
                </a>
            </div>
        </div>
    </section>
</template>
