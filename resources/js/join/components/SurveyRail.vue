<script setup>
/**
 * The progress rail in the onboarding header. Blade decides each step's label,
 * hint, URL and whether it is reachable; this component only decides how a
 * done, current or locked step looks — the Vue twin of
 * `resources/views/components/onboarding-progress.blade.php`, kept in step with
 * it (the signed-in approval page still renders the Blade one).
 */
import { computed } from 'vue';
import { format } from '../format';

const props = defineProps({
    config: { type: Object, required: true },
});

const copy = computed(() => props.config.copy ?? {});
const steps = computed(() => props.config.rail ?? []);
const total = computed(() => steps.value.length);
const current = computed(() => Number(props.config.currentGroup ?? 1));
const answeredCount = computed(() => Number(props.config.answered ?? 0));
const questionTotal = computed(() => Number(props.config.questions ?? props.config.total ?? 0));
// One unit more than the questions: the account form at the end of the flow.
const unitTotal = computed(() => Number(props.config.railUnits ?? questionTotal.value));
const question = computed(() => Number(props.config.index ?? 0) || null);
const completedCount = computed(() => steps.value.filter((step) => step.done).length);

const active = computed(() => steps.value.find((step) => step.number === current.value) ?? steps.value[0] ?? null);

const percent = computed(() => {
    if (unitTotal.value > 0) {
        return (answeredCount.value / unitTotal.value) * 100;
    }

    return total.value > 0 ? ((current.value - 1) / total.value) * 100 : 0;
});

const clampPercent = (value) => Math.max(0, Math.min(100, Math.round(value)));

// How wide one segment is: a phone shows the finished units in emerald and the
// unit being worked on now in primary, mirroring the desktop rail.
const stepShare = computed(() => {
    if (unitTotal.value > 0) {
        return 100 / unitTotal.value;
    }

    return total.value > 0 ? 100 / total.value : 0;
});

const donePercent = computed(() => clampPercent(percent.value));

const activePercent = computed(() => {
    const width = clampPercent(Math.min(stepShare.value, 100 - donePercent.value));

    return width === 0 && donePercent.value === 0 ? 2 : width;
});

const mobileTitle = computed(() => {
    if (question.value && questionTotal.value) {
        return format(copy.value.questionOf, { number: question.value, total: questionTotal.value });
    }

    if (questionTotal.value) {
        return copy.value.lastStep ?? '';
    }

    return format(copy.value.stepOf, { number: current.value, total: total.value });
});

const mobileCounter = computed(() => (unitTotal.value
    ? `${answeredCount.value}/${unitTotal.value}`
    : `${completedCount.value}/${total.value}`));

function state(step) {
    if (step.done) {
        return 'done';
    }

    return step.number === current.value ? 'current' : 'todo';
}

function circleClasses(step) {
    return {
        done: 'bg-emerald-500 text-white',
        current: 'bg-primary text-white',
        todo: 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
    }[state(step)];
}

function barClasses(step) {
    return {
        done: 'bg-emerald-400',
        current: 'bg-primary',
        todo: 'bg-transparent',
    }[state(step)];
}

function labelClasses(step) {
    return {
        done: 'text-slate-500 dark:text-slate-400',
        current: 'text-primary',
        todo: 'text-slate-500 dark:text-slate-400',
    }[state(step)];
}

function stepProgress(step) {
    const ratio = step.progress ?? (step.done ? 1 : 0);

    return Math.max(0, Math.min(100, Math.round(ratio * 100)));
}
</script>

<template>
    <div class="border-t border-slate-200/70 bg-white/80 dark:border-slate-800/70 dark:bg-slate-950/60">
        <div class="mx-auto w-full max-w-3xl px-4 py-2.5 sm:px-6 sm:py-3 lg:max-w-5xl xl:max-w-6xl">
            <div class="flex items-center justify-between gap-3 sm:hidden">
                <p class="min-w-0 text-xs font-semibold text-slate-600 dark:text-slate-300">
                    <span class="text-primary">{{ mobileTitle }}</span>
                    <span class="mx-1 text-slate-300 dark:text-slate-600" aria-hidden="true">·</span>
                    <span class="truncate align-bottom">{{ active?.label ?? '' }}</span>
                </p>
                <p class="shrink-0 text-[11px] font-semibold text-slate-400">{{ mobileCounter }}</p>
            </div>

            <div
                class="mt-1.5 flex h-1 overflow-hidden rounded-full bg-slate-200/80 dark:bg-slate-800 sm:hidden"
                role="progressbar"
                aria-valuemin="0"
                aria-valuemax="100"
                :aria-valuenow="donePercent"
                :aria-label="copy.progressLabel"
            >
                <div
                    class="h-full shrink-0 bg-emerald-400 transition-all duration-500 motion-reduce:transition-none"
                    :style="{ width: `${donePercent}%` }"
                ></div>
                <div
                    class="h-full shrink-0 bg-primary transition-all duration-500 motion-reduce:transition-none"
                    :style="{ width: `${activePercent}%` }"
                ></div>
            </div>

            <ol class="hidden items-start gap-1 sm:flex" :aria-label="copy.railLabel">
                <li v-for="step in steps" :key="step.key" class="min-w-0 flex-1">
                    <a
                        :href="step.reachable ? step.url : undefined"
                        :aria-disabled="step.reachable ? undefined : 'true'"
                        :tabindex="step.reachable ? undefined : -1"
                        :aria-current="step.number === current ? 'step' : undefined"
                        class="group flex flex-col gap-1.5 rounded-lg px-1 py-1 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40"
                        :class="step.reachable ? '' : 'pointer-events-none opacity-60'"
                    >
                        <span class="flex items-center gap-1.5">
                            <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-[10px] font-bold" :class="circleClasses(step)">
                                <svg v-if="state(step) === 'done'" class="h-3 w-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7" />
                                </svg>
                                <template v-else>{{ step.number }}</template>
                            </span>
                            <span class="h-1 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                                <span
                                    class="block h-full rounded-full transition-all duration-500 motion-reduce:transition-none"
                                    :class="barClasses(step)"
                                    :style="{ width: `${stepProgress(step)}%` }"
                                ></span>
                            </span>
                        </span>
                        <span class="truncate text-[11px] font-semibold leading-4" :class="labelClasses(step)">{{ step.label }}</span>
                    </a>
                </li>
            </ol>
        </div>
    </div>
</template>
