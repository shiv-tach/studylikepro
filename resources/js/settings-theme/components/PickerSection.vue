<script setup>
/**
 * The card every theme picker sits in: one heading, one hint and the controls.
 * Keeps the four sections (presets, accent, mode, sidebar) visually identical.
 */
defineProps({
    title: { type: String, required: true },
    hint: { type: String, default: '' },
});
</script>

<template>
    <section class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <div class="mb-6">
            <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ title }}</h3>
            <p v-if="hint" class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ hint }}</p>
        </div>

        <slot />
    </section>
</template>
