<script setup>
/**
 * Five stars with the rating spoken for a screen reader. The glyphs are
 * decoration; `label` is the fact ("5 out of 5").
 */
defineProps({
    filled: { type: String, default: '' },
    empty: { type: String, default: '' },
    label: { type: String, default: null },
});
</script>

<template>
    <span class="inline-flex items-baseline text-sm text-amber-500">
        <span aria-hidden="true">
            {{ filled }}<span class="text-slate-300 dark:text-slate-600">{{ empty }}</span>
        </span>
        <span v-if="label" class="sr-only">{{ label }}</span>
    </span>
</template>
