<script setup>
/**
 * What is left of a lesson plan: the balance Blade counted, how much of the
 * allocation the sessions have used, and whether the student can still book.
 * A plan with time left reads in the accent colour; a spent one reads as done.
 */
import { computed } from 'vue';
import { usePanel } from '../../shared/composables/usePanel';
import { HEADING_TEXT, MUTED_TEXT } from '../../shared/styles';
import AppIcon from '../../shared/components/AppIcon.vue';

const props = defineProps({
    plan: { type: Object, required: true },
});

const panel = usePanel();

const valueTone = computed(() => (props.plan.remainingTone === 'primary'
    ? 'text-primary'
    : 'text-emerald-600 dark:text-emerald-400'));

// Literal classes only: Tailwind cannot compile a class it never sees.
const capacityTones = {
    primary: 'bg-primary/10 text-primary',
    amber: 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
};
const capacityClass = computed(() => capacityTones[props.plan.sessionsLeftTone] ?? capacityTones.primary);
</script>

<template>
    <section :class="panel" aria-labelledby="teacher-order-plan-heading">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 id="teacher-order-plan-heading" :class="['text-sm font-bold', HEADING_TEXT]">
                    {{ plan.title }}
                </h2>
                <p :class="['mt-1 text-sm', MUTED_TEXT]">{{ plan.intro }}</p>
            </div>

            <div class="text-right">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ plan.remainingTitle }}</p>
                <p :class="[valueTone, 'mt-1 text-2xl font-extrabold tracking-tight']">{{ plan.remainingValue }}</p>
            </div>
        </div>

        <div class="mt-4">
            <div class="flex items-center justify-between text-xs text-slate-400">
                <span>{{ plan.progress.label }}</span>
                <span class="tabular-nums">{{ plan.progress.percent }}%</span>
            </div>
            <div class="mt-1.5 h-2 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                <div
                    class="h-full rounded-full transition-all duration-300"
                    :class="plan.remainingTone === 'primary' ? 'bg-primary' : 'bg-emerald-500'"
                    :style="{ width: `${plan.progress.percent}%` }"
                />
            </div>
        </div>

        <ul class="mt-4 flex flex-wrap gap-3 text-xs">
            <li class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 font-semibold text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                <span class="h-3.5 w-3.5" aria-hidden="true"><AppIcon name="calendar" /></span>
                {{ plan.sessionsLabel }}
            </li>
            <li
                v-if="plan.sessionsLeftLabel"
                class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 font-semibold"
                :class="capacityClass"
            >
                <span class="h-3.5 w-3.5" aria-hidden="true"><AppIcon name="clock" /></span>
                {{ plan.sessionsLeftLabel }}
            </li>
        </ul>
    </section>
</template>
