<script setup>
/**
 * The lesson's paperwork: who it is for and the identifiers support will ask
 * for. Blade pre-formats every value.
 */
import UiChip from '../../shared/components/UiChip.vue';
import { usePanel } from '../../shared/composables/usePanel';
import { useCopy } from '../composables/usePageContext';

defineProps({
    lesson: { type: Object, required: true },
});

const copy = useCopy();
const panel = usePanel();
</script>

<template>
    <section :class="panel" aria-labelledby="booking-details-title">
        <h2 id="booking-details-title" class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ copy.details?.title }}</h2>

        <p class="mt-3 text-base font-bold text-slate-900 dark:text-slate-50">{{ lesson.dateLabel }}, {{ lesson.timeRange }}</p>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ lesson.durationLabel }} · {{ lesson.timezone }}</p>

        <dl class="mt-4 space-y-3 border-t border-slate-200 pt-4 text-sm dark:border-slate-800">
            <div class="flex items-start justify-between gap-3">
                <dt class="text-slate-500 dark:text-slate-400">{{ copy.details?.learner }}</dt>
                <dd class="text-right font-semibold text-slate-700 dark:text-slate-200">
                    {{ lesson.learner }}
                    <UiChip v-if="lesson.learnerGrade" class="ml-1" :tone="'slate'">{{ lesson.learnerGrade }}</UiChip>
                </dd>
            </div>

            <div class="flex items-start justify-between gap-3">
                <dt class="text-slate-500 dark:text-slate-400">{{ copy.details?.account }}</dt>
                <dd class="text-right font-semibold text-slate-700 dark:text-slate-200">{{ lesson.account }}</dd>
            </div>

            <div v-if="lesson.subject" class="flex items-start justify-between gap-3">
                <dt class="text-slate-500 dark:text-slate-400">{{ copy.details?.subject }}</dt>
                <dd class="text-right font-semibold text-slate-700 dark:text-slate-200">{{ lesson.subject }}</dd>
            </div>

            <div v-if="lesson.lesson" class="flex items-start justify-between gap-3">
                <dt class="text-slate-500 dark:text-slate-400">{{ copy.details?.lesson }}</dt>
                <dd class="text-right font-semibold text-slate-700 dark:text-slate-200">{{ lesson.lesson }}</dd>
            </div>

            <div class="flex items-start justify-between gap-3">
                <dt class="text-slate-500 dark:text-slate-400">{{ copy.details?.bookedOn }}</dt>
                <dd class="text-right font-semibold text-slate-700 dark:text-slate-200">{{ lesson.bookedOn }}</dd>
            </div>

            <div class="flex items-start justify-between gap-3">
                <dt class="text-slate-500 dark:text-slate-400">{{ copy.details?.reference }}</dt>
                <dd class="text-right font-semibold tabular-nums text-slate-700 dark:text-slate-200">{{ lesson.reference }}</dd>
            </div>
        </dl>
    </section>
</template>
