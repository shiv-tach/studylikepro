<script setup>
import { flowSteps } from '../content';
import { usePanel } from '../composables/usePanel';

const panel = usePanel();
</script>

<template>
    <section :class="panel" aria-labelledby="dashboard-flow">
        <h2 id="dashboard-flow" class="text-base font-bold text-slate-800 dark:text-slate-200">
            The Studylikepro flow
        </h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">One journey from question to lesson.</p>

        <ul class="mt-5 space-y-4">
            <li v-for="step in flowSteps" :key="step.title" class="flex items-start gap-3">
                <span
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary/10 text-base"
                    aria-hidden="true"
                >
                    {{ step.emoji }}
                </span>
                <div>
                    <h3 class="text-sm font-semibold text-slate-800 dark:text-slate-100">{{ step.title }}</h3>
                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ step.description }}</p>
                </div>
            </li>
        </ul>
    </section>
</template>
