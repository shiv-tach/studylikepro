<script setup>
/**
 * The draft state of the step, rendered wherever the layout has room for it.
 * The labels come from Blade, so every step speaks the same language.
 */
import { computed } from 'vue';

const props = defineProps({
    status: { type: String, default: 'idle' },
    labels: { type: Object, default: () => ({}) },
});

const tone = computed(() => ({
    idle: 'text-slate-400',
    dirty: 'text-amber-600 dark:text-amber-400',
    saving: 'text-slate-400',
    saved: 'text-emerald-600 dark:text-emerald-400',
    offline: 'text-amber-600 dark:text-amber-400',
    error: 'text-rose-600 dark:text-rose-400',
}[props.status] ?? 'text-slate-400'));

const label = computed(() => ({
    idle: props.labels.saved ?? '',
    dirty: props.labels.dirty ?? '',
    saving: props.labels.saving ?? '',
    saved: props.labels.saved ?? '',
    offline: props.labels.offline ?? '',
    error: props.labels.error ?? '',
}[props.status] ?? ''));
</script>

<template>
    <p v-if="label" class="flex items-center gap-1.5 text-[11px] font-semibold" :class="tone" role="status" aria-live="polite">
        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-current" aria-hidden="true"></span>
        <span class="truncate">{{ label }}</span>
    </p>
</template>
