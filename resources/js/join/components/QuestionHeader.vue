<script setup>
/**
 * The question above the card: which question this is, how far the teacher has
 * got, the question itself and its one-line explanation.
 */
import { computed } from 'vue';
import { format } from '../format';

const props = defineProps({
    index: { type: Number, default: 0 },
    total: { type: Number, default: 0 },
    answered: { type: Number, default: 0 },
    percent: { type: Number, default: 0 },
    title: { type: String, default: '' },
    body: { type: String, default: '' },
    copy: { type: Object, default: () => ({}) },
});

const counter = computed(() => format(props.copy.questionOf, { number: props.index, total: props.total }));
</script>

<template>
    <div class="survey-enter">
        <div class="flex flex-wrap items-center gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-primary/10 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wider text-primary">
                {{ counter }}
            </span>

            <span v-if="answered > 0" class="text-[11px] font-semibold text-slate-400">
                {{ percent }}% {{ copy.answered }}
            </span>
        </div>

        <h1 class="mt-2 text-2xl font-bold leading-tight text-slate-900 dark:text-slate-100 sm:text-3xl">
            {{ title }}
        </h1>
        <p class="mt-2 max-w-2xl text-sm leading-relaxed text-slate-500 dark:text-slate-400">
            {{ body }}
        </p>
    </div>
</template>
