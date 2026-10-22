<script setup>
/**
 * The order's life as a rail: placed, paid, valid until, completed or cancelled.
 * Blade decides which events exist and whether they already happened, so a
 * pending hold, a finished plan and a cancelled order all read correctly without
 * this component knowing any status names.
 */
import { usePanel } from '../../shared/composables/usePanel';
import { HEADING_TEXT, MUTED_TEXT } from '../../shared/styles';
import AppIcon from '../../shared/components/AppIcon.vue';
import { useCopy } from '../composables/usePageContext';

defineProps({
    steps: { type: Array, default: () => [] },
});

const copy = useCopy();
const panel = usePanel();

// Literal classes only: Tailwind cannot compile a class it never sees.
const dotTones = {
    primary: 'bg-primary/10 text-primary',
    emerald: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300',
    amber: 'bg-amber-100 text-amber-700 dark:bg-amber-950/40 dark:text-amber-300',
    rose: 'bg-rose-100 text-rose-700 dark:bg-rose-950/40 dark:text-rose-300',
    slate: 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
};

const dotClass = (tone, done) => [
    dotTones[tone] ?? dotTones.slate,
    done ? '' : 'opacity-60',
];
</script>

<template>
    <section :class="panel" aria-labelledby="teacher-order-timeline-heading">
        <h2 id="teacher-order-timeline-heading" :class="['text-sm font-bold', HEADING_TEXT]">
            {{ copy.panels?.timeline }}
        </h2>

        <ol class="mt-5 space-y-5 border-l border-slate-200 pl-6 dark:border-slate-800">
            <li v-for="step in steps" :key="step.key" class="relative">
                <span
                    class="absolute -left-[2.05rem] top-0 flex h-8 w-8 items-center justify-center rounded-full p-2 ring-4 ring-white dark:ring-slate-900"
                    :class="dotClass(step.tone, step.done)"
                    aria-hidden="true"
                >
                    <AppIcon :name="step.icon" />
                </span>

                <p :class="['text-xs font-semibold uppercase tracking-wider', MUTED_TEXT]">{{ step.label }}</p>
                <p :class="['mt-0.5 text-sm font-semibold', HEADING_TEXT]">{{ step.value }}</p>
                <p v-if="step.note" class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ step.note }}</p>
            </li>
        </ol>
    </section>
</template>
