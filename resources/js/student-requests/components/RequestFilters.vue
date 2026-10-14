<script setup>
/**
 * The status buckets the list can be narrowed to. Buckets with nothing in them
 * are shown but disabled, so the student can see the shape of their history
 * without clicking into an empty list. Blade labels and counts every bucket,
 * so the Vue side never deals with the status enum.
 */
import { usePanel } from '../../shared/composables/usePanel';
import { useCopy } from '../composables/usePageContext';

defineProps({
    filters: { type: Array, default: () => [] },
    active: { type: String, required: true },
});

const emit = defineEmits(['select']);

const copy = useCopy();
const panel = usePanel();
</script>

<template>
    <section :class="panel" aria-labelledby="requests-filters">
        <p id="requests-filters" class="text-xs font-semibold uppercase tracking-wider text-slate-400">
            {{ copy.filtersLabel }}
        </p>

        <div class="mt-3 flex flex-wrap gap-2">
            <button
                v-for="filter in filters"
                :key="filter.key"
                type="button"
                :aria-pressed="filter.key === active"
                :disabled="filter.key !== 'all' && filter.count === 0"
                class="inline-flex items-center gap-2 rounded-xl border px-3 py-1.5 text-xs font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 disabled:cursor-not-allowed disabled:opacity-40"
                :class="filter.key === active
                    ? 'border-primary/40 bg-primary/10 text-primary'
                    : 'border-slate-200/80 text-slate-500 hover:border-primary/40 hover:text-primary dark:border-slate-800 dark:text-slate-400'"
                @click="emit('select', filter.key)"
            >
                {{ filter.label }}
                <span class="rounded-full bg-slate-100 px-1.5 tabular-nums text-slate-500 dark:bg-slate-800 dark:text-slate-400">
                    {{ filter.count }}
                </span>
            </button>
        </div>
    </section>
</template>
