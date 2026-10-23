<script setup>
/**
 * What the week adds up to and the only submit button on the page. The button
 * stays disabled until at least one complete range exists, so the server never
 * has to reject an empty week after a full page round-trip.
 */
import { usePanel } from '../../shared/composables/usePanel';
import UiSectionHeading from '../../shared/components/UiSectionHeading.vue';
import { format, useCopy } from '../composables/usePageContext';
import { useWeekEditor } from '../composables/useWeekEditor';
import { GHOST_BUTTON, PRIMARY_BUTTON } from '../styles';

const copy = useCopy();
const panel = usePanel();

const {
    dirty,
    submitting,
    canSave,
    daysPerWeek,
    hoursLabel,
    lessonsPerWeek,
    undo,
} = useWeekEditor();
</script>

<template>
    <section :class="panel" class="space-y-4">
        <UiSectionHeading :title="copy.summaryTitle" icon="clock" level="h3" />

        <dl class="space-y-2 text-sm">
            <div class="flex items-center justify-between gap-3">
                <dt class="text-slate-500 dark:text-slate-400">{{ copy.daysCountLabel }}</dt>
                <dd class="font-semibold text-slate-800 dark:text-slate-100">{{ format(copy.daysCount, { count: daysPerWeek }) }}</dd>
            </div>
            <div class="flex items-center justify-between gap-3">
                <dt class="text-slate-500 dark:text-slate-400">{{ copy.hoursLabel }}</dt>
                <dd class="font-semibold text-slate-800 dark:text-slate-100">{{ format(copy.weeklyHours, { hours: hoursLabel }) }}</dd>
            </div>
            <div class="flex items-center justify-between gap-3">
                <dt class="text-slate-500 dark:text-slate-400">{{ copy.lessonsLabel }}</dt>
                <dd class="font-semibold text-slate-800 dark:text-slate-100">{{ format(copy.lessonsPreview, { count: lessonsPerWeek }) }}</dd>
            </div>
        </dl>

        <p
            class="text-xs font-semibold"
            :class="dirty ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400'"
            role="status"
        >
            {{ dirty ? copy.unsaved : copy.saved }}
        </p>

        <p v-if="!canSave" class="text-xs font-medium text-amber-600 dark:text-amber-400">
            {{ copy.emptySubmit }}
        </p>

        <button type="submit" :class="[PRIMARY_BUTTON, 'w-full']" :disabled="!canSave || submitting">
            {{ submitting ? copy.saving : copy.save }}
        </button>

        <button v-if="dirty" type="button" :class="[GHOST_BUTTON, 'w-full']" @click="undo">
            {{ copy.undo }}
        </button>
    </section>
</template>
