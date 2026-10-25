<script setup>
/**
 * The finder is scoped to the grade from the student's learning profile, which
 * onboarding normally sets. The page no longer explains that scope, so the only
 * thing left to say is the rare case where the grade is missing and the list
 * cannot be narrowed at all.
 */
import { useCopy, useUrls } from '../composables/usePageContext';

defineProps({
    grade: { type: Object, default: null },
});

const copy = useCopy();
const urls = useUrls();

// The amber notice is a warning surface, not a theme card, so it keeps its own
// translucent border and the dark-mode pair the Blade page used.
const noticeClass =
    'rounded-2xl border border-amber-200/80 bg-amber-50 p-4 text-sm text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300';
</script>

<template>
    <div v-if="!grade" :class="noticeClass" role="status">
        {{ copy.noGrade }}
        <a :href="urls.profile" class="font-semibold underline">{{ copy.completeProfile }}</a>
    </div>
</template>
