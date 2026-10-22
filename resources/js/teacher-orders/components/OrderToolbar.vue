<script setup>
/**
 * Search sits above the list as a real GET form, so Enter submits it, the result
 * is a URL the teacher can share or bookmark, and the active status survives the
 * search. The clear link drops only the term and keeps the status filter. The
 * layout control at the end swaps the book between the default list and a card
 * grid.
 */
import { computed } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';
import { countLabel, format, useCopy, useUrls } from '../composables/usePageContext';

const props = defineProps({
    search: { type: Object, required: true },
    visible: { type: Number, default: 0 },
    total: { type: Number, default: 0 },
    filtered: { type: Boolean, default: false },
    view: { type: String, default: 'list' },
});

const emit = defineEmits(['update:view']);

const copy = useCopy();
const urls = useUrls();

const views = [
    { key: 'list', icon: 'list', label: () => copy.toolbar?.viewList },
    { key: 'card', icon: 'grid', label: () => copy.toolbar?.viewCard },
];

// A filtered view reports how many matched; an unfiltered one reports the whole
// book and, when it spans several pages, how much of it is on screen.
const resultLabel = computed(() => {
    if (props.filtered) {
        return countLabel(copy.results?.filteredLabel, props.total);
    }

    if (props.total > props.visible) {
        return format(copy.results?.showing, { visible: props.visible, total: props.total });
    }

    return countLabel(copy.results?.label, props.total);
});
</script>

<template>
    <div class="flex flex-col gap-3 rounded-2xl border border-slate-200/80 bg-white p-3 shadow-sm dark:border-slate-800/80 dark:bg-slate-900 lg:flex-row lg:items-center">
        <form method="GET" :action="urls.index" role="search" class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
            <input v-if="search.status" type="hidden" name="status" :value="search.status">

            <div class="relative min-w-0 flex-1">
                <label for="teacher-orders-search" class="sr-only">{{ search.label }}</label>
                <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400" aria-hidden="true">
                    <span class="h-4 w-4"><AppIcon name="search" /></span>
                </span>
                <input
                    id="teacher-orders-search"
                    :value="search.value"
                    type="search"
                    name="search"
                    autocomplete="off"
                    :placeholder="search.placeholder"
                    class="block w-full rounded-xl border-slate-300 bg-white py-2 pl-9 pr-3 text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                >
            </div>

            <button
                type="submit"
                class="inline-flex shrink-0 items-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm shadow-primary/20 transition-colors hover:bg-primary/90 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
            >
                {{ search.submit }}
            </button>
        </form>

        <p class="shrink-0 px-1 text-xs font-medium text-slate-500 dark:text-slate-400" role="status">
            {{ resultLabel }}
        </p>

        <a
            v-if="filtered"
            :href="urls.index"
            class="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-slate-500 transition-colors hover:text-primary dark:text-slate-400"
        >
            <span class="h-3.5 w-3.5" aria-hidden="true"><AppIcon name="close" /></span>
            {{ copy.empty?.filteredCta }}
        </a>

        <div
            role="group"
            :aria-label="copy.toolbar?.viewLabel"
            class="flex shrink-0 self-end rounded-xl bg-slate-100 p-1 dark:bg-slate-800 lg:self-auto"
        >
            <button
                v-for="option in views"
                :key="option.key"
                type="button"
                :aria-pressed="view === option.key ? 'true' : 'false'"
                :aria-label="option.label()"
                :title="option.label()"
                class="rounded-lg p-2 transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
                :class="view === option.key
                    ? 'bg-white text-slate-800 shadow-sm dark:bg-slate-900 dark:text-slate-100'
                    : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                @click="emit('update:view', option.key)"
            >
                <span class="block h-4 w-4" aria-hidden="true"><AppIcon :name="option.icon" /></span>
            </button>
        </div>
    </div>
</template>
