<script setup>
/**
 * A catalog subject as a selectable tile. The real checkbox carries the
 * `subject_ids[]` name, so the form submits exactly what the teacher picked.
 */
import AppIcon from '../../shared/components/AppIcon.vue';

defineProps({
    subject: { type: Object, required: true },
    selected: { type: Boolean, default: false },
});

const emit = defineEmits(['change']);
</script>

<template>
    <label
        class="group flex cursor-pointer items-center gap-3 rounded-xl border px-4 py-3 transition-all focus-within:ring-2 focus-within:ring-primary/40"
        :class="selected
            ? 'border-primary bg-primary/5 shadow-sm shadow-primary/10 dark:bg-primary/10'
            : 'border-slate-200/80 hover:border-primary/40 hover:bg-slate-50/60 dark:border-slate-800/80 dark:hover:bg-slate-800/40'"
    >
        <input
            type="checkbox"
            name="subject_ids[]"
            :value="subject.id"
            :checked="selected"
            class="sr-only"
            @change="emit('change')"
        >

        <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white text-lg shadow-sm dark:bg-slate-800" aria-hidden="true">
            {{ subject.icon }}
        </span>

        <span class="min-w-0 flex-1 truncate text-sm font-semibold text-slate-700 dark:text-slate-200">
            {{ subject.name }}
        </span>

        <span
            class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full border transition-colors"
            :class="selected
                ? 'border-primary bg-primary text-white'
                : 'border-slate-300 text-transparent dark:border-slate-600'"
            aria-hidden="true"
        >
            <span class="h-3 w-3"><AppIcon name="check" /></span>
        </span>
    </label>
</template>
