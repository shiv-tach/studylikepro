<script setup>
/**
 * The lesson's lifecycle, built by Booking::timeline(). Blade keeps the step
 * order, labels, descriptions and timestamps — including the cancellation note
 * — and this panel only draws them as a rail.
 */
import { usePanel } from '../../shared/composables/usePanel';
import { useCopy } from '../composables/usePageContext';

defineProps({
    timeline: { type: Object, required: true },
});

const copy = useCopy();
const panel = usePanel();
</script>

<template>
    <section :class="panel" aria-labelledby="booking-timeline-title">
        <h2 id="booking-timeline-title" class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ copy.timeline?.title }}</h2>

        <ol class="mt-4 space-y-5 border-l border-slate-200 pl-6 dark:border-slate-800">
            <li v-for="step in timeline.steps" :key="step.key" class="relative">
                <span
                    class="absolute -left-[31px] top-1 flex h-3.5 w-3.5 items-center justify-center rounded-full border-2 border-white dark:border-slate-900"
                    :class="step.at ? 'bg-primary' : 'bg-slate-300 dark:bg-slate-700'"
                ></span>

                <p class="text-sm font-semibold text-slate-800 dark:text-slate-100">
                    {{ step.label }}
                    <span class="font-normal text-slate-400">· {{ step.at ?? copy.timeline?.pending }}</span>
                </p>
                <p class="mt-0.5 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ step.description }}</p>

                <p
                    v-if="step.key === 'cancelled' && timeline.cancellationReason"
                    class="mt-1 text-xs italic text-slate-500 dark:text-slate-400"
                >
                    “{{ timeline.cancellationReason }}”
                </p>
            </li>
        </ol>
    </section>
</template>
