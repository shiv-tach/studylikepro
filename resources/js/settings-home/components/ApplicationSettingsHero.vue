<script setup>
/**
 * The settings home's intro panel: the accent icon tile, the page title and the
 * one-paragraph explanation of what lives below.
 */
import AppIcon from '../../shared/components/AppIcon.vue';

defineProps({
    title: { type: String, required: true },
    description: { type: String, default: '' },
    icon: { type: String, default: 'cog' },
});
</script>

<template>
    <div class="rounded-2xl border border-slate-200 bg-white p-6 dark:border-slate-800 dark:bg-slate-900">
        <div class="flex items-start gap-4">
            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary/10 p-3 text-primary">
                <AppIcon :name="icon" />
            </div>
            <div>
                <h3 class="text-lg font-bold text-slate-800 dark:text-slate-100">{{ title }}</h3>
                <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ description }}</p>
            </div>
        </div>
    </div>
</template>
