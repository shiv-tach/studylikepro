<script setup>
/**
 * What the week adds up to and the only submit button of the week form. The
 * button stays disabled until at least one complete range exists, so the server
 * never has to reject an empty week after a full page round-trip.
 */
import { usePanel } from '../../composables/usePanel';
import UiSectionHeading from '../../components/UiSectionHeading.vue';
import { format, useCopy } from '../context';
import { useWeekEditor } from '../useWeekEditor';
import { GHOST_BUTTON, PRIMARY_BUTTON } from '../styles';

const copy = useCopy();
const panel = usePanel();

const props = defineProps({
    // The onboarding wizard renders its own sticky action bar, so the summary
    // card only shows the numbers there.
    showSubmit: { type: Boolean, default: true },
});

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
            v-if="showSubmit"
            class="text-xs font-semibold"
            :class="dirty ? 'text-amber-600 dark:text-amber-400' : 'text-slate-400'"
            role="status"
        >
            {{ dirty ? copy.unsaved : copy.saved }}
        </p>

        <p v-if="!canSave" class="text-xs font-medium text-amber-600 dark:text-amber-400">
            {{ copy.emptySubmit }}
        </p>

        <button v-if="showSubmit" type="submit" :class="[PRIMARY_BUTTON, 'w-full']" :disabled="!canSave || submitting">
            {{ submitting ? copy.saving : copy.save }}
        </button>

        <button v-if="showSubmit && dirty" type="button" :class="[GHOST_BUTTON, 'w-full']" @click="undo">
            {{ copy.undo }}
        </button>

        <p v-if="copy.weekNote" class="text-xs leading-relaxed text-slate-400">{{ copy.weekNote }}</p>
    </section>
</template>
