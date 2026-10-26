<script setup>
/**
 * Shown when the page was pre-filled from an unsaved draft. It says where the
 * answers came from and offers a clean start, so a teacher is never surprised
 * by older text appearing in a field.
 */
import AppIcon from '../../components/AppIcon.vue';

defineProps({
    title: { type: String, required: true },
    body: { type: String, default: '' },
    discardLabel: { type: String, default: 'Start over' },
});

defineEmits(['discard']);
</script>

<template>
    <div class="flex items-start gap-3 rounded-2xl border border-amber-200/80 bg-amber-50/80 p-4 text-sm text-amber-900 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-200">
        <span class="mt-0.5 h-5 w-5 shrink-0 text-amber-500"><AppIcon name="clock" /></span>

        <div class="min-w-0 flex-1">
            <p class="font-semibold">{{ title }}</p>
            <p v-if="body" class="mt-1 text-xs leading-relaxed text-amber-800/90 dark:text-amber-300/90">{{ body }}</p>
        </div>

        <button
            type="button"
            class="shrink-0 rounded-lg px-2 py-1 text-xs font-bold text-amber-700 underline-offset-2 transition-colors hover:bg-amber-100 hover:underline dark:text-amber-300 dark:hover:bg-amber-900/40"
            @click="$emit('discard')"
        >
            {{ discardLabel }}
        </button>
    </div>
</template>
