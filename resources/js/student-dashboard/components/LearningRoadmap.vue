<script setup>
import { computed } from 'vue';
import { roadmapSteps } from '../content';
import { usePanel } from '../../shared/composables/usePanel';
import { CHIP } from '../../shared/styles';
import AppIcon from '../../shared/components/AppIcon.vue';

const props = defineProps({
    completed: { type: Array, default: () => [] },
    percent: { type: Number, default: 0 },
});

const panel = usePanel();

const steps = computed(() => {
    const done = new Set(props.completed);
    let nextClaimed = false;

    return roadmapSteps.map((step) => {
        const isDone = done.has(step.key);
        const isNext = !isDone && !nextClaimed;

        if (isNext) {
            nextClaimed = true;
        }

        return { ...step, isDone, isNext };
    });
});
</script>

<template>
    <section :class="panel" aria-labelledby="dashboard-roadmap">
        <div class="flex items-start justify-between gap-3">
            <div>
                <h2 id="dashboard-roadmap" class="text-base font-bold text-slate-800 dark:text-slate-200">
                    Your path to your first lesson
                </h2>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">
                    Each step ships in a phase of the build plan, so you can watch the platform come together.
                </p>
            </div>
            <span :class="[CHIP, 'bg-primary/10 text-primary']">{{ percent }}%</span>
        </div>

        <div class="mt-4 h-1.5 w-full overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800" role="presentation">
            <div
                class="h-full rounded-full bg-primary transition-all duration-700 ease-out"
                :style="{ width: `${percent}%` }"
            ></div>
        </div>

        <ol class="mt-5 space-y-4">
            <li v-for="(step, index) in steps" :key="step.key" class="flex items-start gap-3">
                <span
                    class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold transition-colors duration-300"
                    :class="
                        step.isDone
                            ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400'
                            : step.isNext
                              ? 'bg-primary text-white ring-4 ring-primary/15'
                              : 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400'
                    "
                >
                    <span v-if="step.isDone" class="h-3.5 w-3.5"><AppIcon name="check-circle" /></span>
                    <template v-else>{{ index + 1 }}</template>
                </span>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ step.title }}</h3>
                        <span :class="[CHIP, 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400']">
                            {{ step.phase }}
                        </span>
                        <span v-if="step.isNext" :class="[CHIP, 'bg-primary/10 text-primary']">You are here</span>
                    </div>
                    <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ step.description }}</p>
                </div>
            </li>
        </ol>
    </section>
</template>
