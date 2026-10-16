<script setup>
/**
 * What a student sees when nothing matched. The copy follows the filters: a
 * picked day means nobody is free then (so widen the window), otherwise the
 * subject or language is too narrow, and the panel offers the way back.
 */
import { usePanel } from '../../shared/composables/usePanel';
import AppIcon from '../../shared/components/AppIcon.vue';
import { useCopy, useUrls } from '../composables/usePageContext';

defineProps({
    hasDate: { type: Boolean, default: false },
    hasFilters: { type: Boolean, default: false },
});

const copy = useCopy();
const urls = useUrls();
const panel = usePanel();
</script>

<template>
    <section :class="[panel, 'flex flex-col items-center gap-4 text-center']" aria-labelledby="finder-empty">
        <span class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10 p-3 text-primary">
            <AppIcon name="search" />
        </span>

        <div>
            <h2 id="finder-empty" class="text-lg font-bold text-slate-800 dark:text-slate-100">
                {{ copy.emptyTitle }}
            </h2>
            <p class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-slate-500 dark:text-slate-400">
                {{ hasDate ? copy.emptyWithDate : copy.emptyBody }}
            </p>
        </div>

        <a
            v-if="hasFilters"
            :href="urls.reset"
            class="inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-primary/20 transition-transform hover:-translate-y-0.5 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
        >
            <span class="h-4 w-4"><AppIcon name="users" /></span>
            {{ copy.clear }}
        </a>
    </section>
</template>
