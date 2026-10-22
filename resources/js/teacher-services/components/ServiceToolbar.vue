<script setup>
/**
 * Search and availability sit above the list. The status control is a real
 * toggle group (aria-pressed) rather than a select, so a teacher can switch
 * between active and paused without opening a menu. The layout control beside
 * it swaps the catalog between the default list and a card grid.
 */
import AppIcon from '../../shared/components/AppIcon.vue';
import { useCopy } from '../composables/usePageContext';
import { PRIMARY_BUTTON } from '../styles';

defineProps({
    search: { type: String, default: '' },
    status: { type: String, default: 'all' },
    view: { type: String, default: 'list' },
    resultLabel: { type: String, default: '' },
    canCreate: { type: Boolean, default: true },
});

const emit = defineEmits(['update:search', 'update:status', 'update:view', 'create']);

const copy = useCopy();

const statuses = [
    { key: 'all', label: () => copy.toolbar?.statusAll },
    { key: 'active', label: () => copy.toolbar?.statusActive },
    { key: 'paused', label: () => copy.toolbar?.statusPaused },
];

const views = [
    { key: 'list', icon: 'list', label: () => copy.toolbar?.viewList },
    { key: 'card', icon: 'grid', label: () => copy.toolbar?.viewCard },
];
</script>

<template>
    <div class="flex flex-col gap-3 rounded-2xl border border-slate-200/80 bg-white p-3 shadow-sm dark:border-slate-800/80 dark:bg-slate-900 lg:flex-row lg:items-center">
        <div class="relative min-w-0 flex-1">
            <label for="teacher-services-search" class="sr-only">{{ copy.toolbar?.searchLabel }}</label>
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-slate-400" aria-hidden="true">
                <span class="h-4 w-4"><AppIcon name="search" /></span>
            </span>
            <input
                id="teacher-services-search"
                :value="search"
                type="search"
                autocomplete="off"
                :placeholder="copy.toolbar?.searchPlaceholder"
                class="block w-full rounded-xl border-slate-300 bg-white py-2 pl-9 pr-9 text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                @input="emit('update:search', $event.target.value)"
            >
            <button
                v-if="search"
                type="button"
                class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400 transition-colors hover:text-slate-600 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 dark:hover:text-slate-200"
                :aria-label="copy.toolbar?.clearSearch"
                @click="emit('update:search', '')"
            >
                <span class="h-4 w-4"><AppIcon name="close" /></span>
            </button>
        </div>

        <div
            role="group"
            :aria-label="copy.toolbar?.statusLabel"
            class="flex shrink-0 rounded-xl bg-slate-100 p-1 dark:bg-slate-800"
        >
            <button
                v-for="option in statuses"
                :key="option.key"
                type="button"
                :aria-pressed="status === option.key ? 'true' : 'false'"
                class="rounded-lg px-3 py-1.5 text-xs font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
                :class="status === option.key
                    ? 'bg-white text-slate-800 shadow-sm dark:bg-slate-900 dark:text-slate-100'
                    : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                @click="emit('update:status', option.key)"
            >
                {{ option.label() }}
            </button>
        </div>

        <p class="shrink-0 px-1 text-xs font-medium text-slate-500 dark:text-slate-400" role="status">
            {{ resultLabel }}
        </p>

        <div
            role="group"
            :aria-label="copy.toolbar?.viewLabel"
            class="flex shrink-0 rounded-xl bg-slate-100 p-1 dark:bg-slate-800"
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

        <button v-if="canCreate" type="button" :class="[PRIMARY_BUTTON, 'shrink-0']" @click="emit('create')">
            <span class="h-4 w-4" aria-hidden="true"><AppIcon name="plus" /></span>
            {{ copy.hero?.cta }}
        </button>
    </div>
</template>
