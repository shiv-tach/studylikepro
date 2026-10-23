<script setup>
/**
 * The three-step onboarding tracker, shared by the profile, availability and
 * verification screens. Blade decides each step's label, hint, URL and whether
 * it is reachable — this component only decides how a done, current or locked
 * step looks, so the three pages can never describe the same step differently.
 *
 * `copy` is passed in rather than injected because each page owns its own copy
 * island; the keys it reads are the same on all three.
 */
import { computed } from 'vue';
import { usePanel } from '../composables/usePanel';
import AppIcon from './AppIcon.vue';

const props = defineProps({
    steps: { type: Array, required: true },
    current: { type: Number, required: true },
    copy: { type: Object, default: () => ({}) },
});

const panel = usePanel();

const completedCount = computed(() => props.steps.filter((step) => step.done).length);
const allDone = computed(() => props.steps.length > 0 && completedCount.value === props.steps.length);

function format(template, values) {
    return Object.entries(values).reduce(
        (text, [key, value]) => text.replace(`:${key}`, String(value)),
        String(template ?? ''),
    );
}

function statusOf(step) {
    if (step.done) {
        return props.copy.stepStatusDone;
    }

    if (step.number === props.current) {
        return props.copy.stepStatusCurrent;
    }

    return step.reachable ? props.copy.stepStatusUpNext : props.copy.stepStatusLocked;
}

function cardClasses(step) {
    if (step.done) {
        return 'border-emerald-200/80 bg-emerald-50/60 dark:border-emerald-900/40 dark:bg-emerald-950/20';
    }

    if (step.number === props.current) {
        return 'border-primary/50 bg-primary/5 dark:border-primary/40 dark:bg-primary/10';
    }

    return 'border-slate-200/80 dark:border-slate-800/80';
}

function circleClasses(step) {
    if (step.done) {
        return 'bg-emerald-500 text-white';
    }

    return step.number === props.current
        ? 'bg-primary text-white'
        : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400';
}

function statusClasses(step) {
    if (step.done) {
        return 'text-emerald-600 dark:text-emerald-400';
    }

    return step.number === props.current ? 'text-primary' : 'text-slate-400';
}
</script>

<template>
    <section :class="panel">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ copy.stepsTitle }}</p>
            <p
                class="text-xs font-semibold"
                :class="allDone ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'"
            >
                {{ format(copy.stepsCompleted, { completed: completedCount, total: steps.length }) }}
            </p>
        </div>

        <ol class="mt-4 grid gap-3 sm:grid-cols-3">
            <li v-for="step in steps" :key="step.number" class="h-full">
                <a
                    :href="step.url"
                    :aria-disabled="!step.reachable ? 'true' : undefined"
                    :tabindex="step.reachable ? undefined : -1"
                    :aria-current="step.number === current ? 'step' : undefined"
                    class="flex h-full items-start gap-3 rounded-xl border p-3.5 transition-colors"
                    :class="[
                        cardClasses(step),
                        step.reachable ? 'hover:border-primary/60' : 'pointer-events-none opacity-70',
                    ]"
                >
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-sm font-bold" :class="circleClasses(step)">
                        <span v-if="step.done" class="h-4 w-4"><AppIcon name="check" /></span>
                        <template v-else>{{ step.number }}</template>
                    </span>

                    <span class="min-w-0 flex-1">
                        <span class="block min-h-8 text-[11px] font-semibold uppercase leading-4 tracking-wide" :class="statusClasses(step)">
                            {{ format(copy.stepLabel, { number: step.number }) }} · {{ statusOf(step) }}
                        </span>
                        <span class="block truncate text-sm font-semibold text-slate-800 dark:text-slate-100">{{ step.label }}</span>
                        <span class="mt-0.5 block text-xs leading-5 text-slate-500 dark:text-slate-400">{{ step.hint }}</span>
                    </span>
                </a>
            </li>
        </ol>
    </section>
</template>
