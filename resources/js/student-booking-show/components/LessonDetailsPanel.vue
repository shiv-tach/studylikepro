<script setup>
/**
 * The lesson at a glance: when it runs in the viewer's timezone, who teaches it,
 * who it is for and the booking reference. Blade has already converted the times
 * and padded the reference, so nothing here reformats a date or an id.
 */
import { usePanel } from '../../shared/composables/usePanel';
import { useCopy } from '../composables/usePageContext';

defineProps({
    details: { type: Object, required: true },
});

const copy = useCopy();
const panel = usePanel();

const termClass = 'text-xs font-semibold uppercase tracking-wider text-slate-400';
const valueClass = 'mt-1 font-semibold text-slate-700 dark:text-slate-200';
</script>

<template>
    <section :class="panel">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p :class="termClass">{{ copy.lessonLabel }}</p>
                <p class="mt-1 text-lg font-bold text-slate-800 dark:text-slate-100">{{ details.dateLine }}</p>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ details.durationLine }}</p>
            </div>

            <div class="text-right">
                <p :class="termClass">{{ copy.teacherLabel }}</p>
                <a :href="details.teacherUrl" class="mt-1 block text-sm font-bold text-primary hover:underline">{{ details.teacherName }}</a>
                <p class="text-xs text-slate-400">{{ details.subjectLine }}</p>
            </div>
        </div>

        <dl class="mt-5 grid gap-4 border-t border-slate-200 pt-5 text-sm sm:grid-cols-3 dark:border-slate-800">
            <div>
                <dt :class="termClass">{{ copy.learnerLabel }}</dt>
                <dd :class="valueClass">{{ details.learner }}</dd>
                <dd v-if="details.learnerGrade" class="text-xs text-slate-500 dark:text-slate-400">{{ details.learnerGrade }}</dd>
            </div>
            <div>
                <dt :class="termClass">{{ copy.bookedOnLabel }}</dt>
                <dd :class="valueClass">{{ details.bookedOn }}</dd>
            </div>
            <div>
                <dt :class="termClass">{{ copy.referenceLabel }}</dt>
                <dd :class="valueClass">{{ details.reference }}</dd>
            </div>
        </dl>

        <p v-if="details.requestUrl" class="mt-5 border-t border-slate-200 pt-4 text-xs text-slate-500 dark:border-slate-800 dark:text-slate-400">
            {{ copy.requestLead }}
            <a :href="details.requestUrl" class="font-semibold text-primary hover:underline">{{ copy.requestLink }}</a>.
        </p>
    </section>
</template>
