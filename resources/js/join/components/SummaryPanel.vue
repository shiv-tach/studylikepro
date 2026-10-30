<script setup>
/**
 * "What you have answered so far" — a disclosure under the question on phones,
 * a sticky card beside it from `lg`. Both render the same list.
 */
import { computed } from 'vue';
import SummaryList from './SummaryList.vue';

const props = defineProps({
    groups: { type: Array, default: () => [] },
    answered: { type: Number, default: 0 },
    questions: { type: Number, default: 0 },
    copy: { type: Object, default: () => ({}) },
});

const counter = computed(() => (props.questions ? `${props.answered}/${props.questions}` : null));
</script>

<template>
    <details class="group rounded-2xl border border-slate-200/80 bg-white p-4 dark:border-slate-800/80 dark:bg-slate-900 lg:hidden">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 text-sm font-semibold text-slate-700 dark:text-slate-200 [&::-webkit-details-marker]:hidden">
            <span class="flex min-w-0 items-center gap-2">
                <span class="text-slate-400" aria-hidden="true">🧾</span>
                <span class="truncate">{{ copy.yourAnswers }}</span>
            </span>
            <span class="flex shrink-0 items-center gap-2">
                <span v-if="counter" class="text-[11px] font-semibold text-slate-400">{{ counter }}</span>
                <svg class="h-4 w-4 text-slate-400 transition-transform duration-200 group-open:rotate-180 motion-reduce:transition-none" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </span>
        </summary>

        <div class="mt-4 border-t border-slate-200/70 pt-4 dark:border-slate-800/70">
            <SummaryList :groups="groups" :copy="copy" />
        </div>
    </details>

    <aside class="hidden lg:sticky lg:top-40 lg:block" aria-labelledby="survey-summary-title">
        <div class="rounded-2xl border border-slate-200/80 bg-white/80 p-4 backdrop-blur dark:border-slate-800/80 dark:bg-slate-900/80">
            <div class="flex items-center justify-between gap-2">
                <h2 id="survey-summary-title" class="text-xs font-bold uppercase tracking-wider text-slate-400">
                    {{ copy.yourAnswers }}
                </h2>
                <span v-if="counter" class="text-[11px] font-semibold text-slate-400">{{ counter }}</span>
            </div>

            <div class="mt-3">
                <SummaryList :groups="groups" :copy="copy" />
            </div>

            <p class="mt-4 rounded-xl bg-slate-50 p-2.5 text-[11px] leading-relaxed text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
                {{ copy.changeNote }}
            </p>
        </div>
    </aside>
</template>
