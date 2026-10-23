<script setup>
/**
 * The ordered "Needs your attention" queue. It only holds lessons that need a
 * decision: a finished lesson to close, one running now, an open classroom, one
 * starting within the hour, or a hold about to lapse. Everything else waits in
 * the agenda.
 */
import AppIcon from '../../shared/components/AppIcon.vue';
import UiSectionHeading from '../../shared/components/UiSectionHeading.vue';
import { usePanel } from '../../shared/composables/usePanel';
import { useCopy, useUrls } from '../composables/usePageContext';
import PriorityRow from './PriorityRow.vue';

defineProps({
    items: { type: Array, default: () => [] },
    /** No lessons are booked at all, so the calm copy must not claim one is next. */
    idle: { type: Boolean, default: false },
});

const copy = useCopy();
const urls = useUrls();
const panel = usePanel();
</script>

<template>
    <section class="space-y-3">
        <UiSectionHeading
            :title="copy.priority?.title"
            :description="copy.priority?.description"
            icon="alert"
            icon-tone="bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300"
        />

        <ol v-if="items.length" class="space-y-3">
            <PriorityRow v-for="item in items" :key="`${item.kind}-${item.id}`" :item="item" />
        </ol>

        <div v-else :class="[panel, 'flex flex-col items-center gap-2 text-center']">
            <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-emerald-100 p-2.5 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                <AppIcon name="check-circle" />
            </span>
            <p class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ copy.priority?.emptyTitle }}</p>
            <p class="max-w-md text-sm text-slate-500 dark:text-slate-400">
                {{ idle ? copy.priority?.emptyBodyIdle : copy.priority?.emptyBody }}
            </p>

            <a
                v-if="idle"
                :href="urls.availability"
                class="mt-1 inline-flex items-center gap-1.5 text-sm font-semibold text-primary hover:underline"
            >
                {{ copy.hero?.availability }}
                <span class="h-4 w-4" aria-hidden="true"><AppIcon name="arrow-right" /></span>
            </a>
        </div>
    </section>
</template>
