<script setup>
/**
 * One finished lesson in the history tab: the outcome, the day, who it was with
 * and what it paid.
 */
import UiChip from '../../shared/components/UiChip.vue';
import { usePanel } from '../../shared/composables/usePanel';
import { useCopy } from '../composables/usePageContext';

defineProps({
    row: { type: Object, required: true },
});

const copy = useCopy();
const panel = usePanel();
</script>

<template>
    <li :class="[panel, 'flex flex-wrap items-center justify-between gap-3 py-4']">
        <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
                <UiChip :tone="row.statusTone">{{ row.statusLabel }}</UiChip>
                <span class="text-xs text-slate-400">{{ row.dateLabel }}, {{ row.timeLabel }}</span>
            </div>

            <p class="mt-1 flex flex-wrap items-baseline gap-x-2 text-sm">
                <a
                    :href="row.url"
                    class="font-bold text-slate-800 transition-colors hover:text-primary dark:text-slate-100"
                >
                    {{ row.learnerName }}
                </a>
                <span v-if="row.learnerGrade" class="text-xs text-slate-400">{{ row.learnerGrade }}</span>
                <span v-if="row.title" class="truncate text-xs text-slate-400">{{ row.title }}</span>
            </p>
        </div>

        <div class="shrink-0 text-right">
            <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ row.payoutLabel }}</p>
            <a :href="row.url" class="text-xs font-semibold text-primary hover:underline">{{ copy.history?.view }}</a>
        </div>
    </li>
</template>
