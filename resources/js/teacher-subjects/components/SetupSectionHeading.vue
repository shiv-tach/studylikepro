<script setup>
/**
 * One numbered block inside a subject setup form: the step eyebrow, the title
 * and the sentence that explains what the teacher is choosing.
 */
defineProps({
    id: { type: String, default: null },
    step: { type: String, default: null },
    title: { type: String, required: true },
    hint: { type: String, default: null },
});
</script>

<template>
    <div class="mb-3">
        <p v-if="step" class="text-[10px] font-bold uppercase tracking-[0.18em] text-primary">{{ step }}</p>
        <h3 :id="id" class="mt-0.5 text-sm font-bold text-slate-800 dark:text-slate-100">{{ title }}</h3>
        <p v-if="hint" class="mt-1 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ hint }}</p>
    </div>
</template>
