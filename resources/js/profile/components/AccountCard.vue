<script setup>
/**
 * The card surface every account section sits in: one bold heading, one hint and
 * the section's form. Keeps the three profile sections visually identical.
 */
defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
});
</script>

<template>
    <section class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <header>
            <h2 class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ title }}</h2>
            <p v-if="description" class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ description }}</p>
        </header>

        <div class="mt-6 max-w-xl">
            <slot />
        </div>
    </section>
</template>
