<script setup>
/**
 * The card for whatever the teacher owes this purchase — a written lesson
 * summary or a marked paper. The whole card is the link, and its tone tells a
 * finished delivery apart from work still in progress.
 */
import { usePanel } from '../../shared/composables/usePanel';
import AppIcon from '../../shared/components/AppIcon.vue';
import UiChip from '../../shared/components/UiChip.vue';

defineProps({
    delivery: { type: Object, required: true },
});

const panel = usePanel(true);
</script>

<template>
    <a :href="delivery.url" :class="[panel, 'block hover:-translate-y-0.5']">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex min-w-0 items-start gap-3">
                <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/10 p-2.5 text-primary">
                    <AppIcon :name="delivery.icon" />
                </span>

                <div class="min-w-0">
                    <h2 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ delivery.title }}</h2>
                    <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ delivery.body }}</p>
                </div>
            </div>

            <span class="flex items-center gap-2">
                <UiChip :tone="delivery.badge.tone">{{ delivery.badge.label }}</UiChip>
                <span class="h-4 w-4 shrink-0 text-slate-400"><AppIcon name="chevron-right" /></span>
            </span>
        </div>
    </a>
</template>
