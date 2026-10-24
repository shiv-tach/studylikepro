<script setup>
/**
 * A disputed delivery, in the student's words. Support resolves this, so the
 * console only states it plainly instead of offering another upload.
 */
import UiAlert from '../../shared/components/UiAlert.vue';

defineProps({
    dispute: { type: Object, required: true },
});
</script>

<template>
    <div class="space-y-2">
        <UiAlert tone="rose" icon="alert">
            <span class="font-semibold">{{ dispute.title }}</span>
        </UiAlert>

        <p class="whitespace-pre-line rounded-2xl border border-rose-200/80 bg-rose-50 p-4 text-sm leading-relaxed text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300">
            {{ dispute.reason }}
        </p>
    </div>
</template>
