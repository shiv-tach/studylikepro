<script setup>
/**
 * Flat versus floating sidebar. Each option draws a miniature sidebar beside a
 * miniature content column so the difference is visible before it is chosen.
 */
import PickerSection from './PickerSection.vue';

defineProps({
    styles: { type: Array, default: () => [] },
    modelValue: { type: String, default: null },
    title: { type: String, required: true },
    hint: { type: String, default: '' },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <PickerSection :title="title" :hint="hint">
        <div class="flex flex-wrap gap-4" role="radiogroup" :aria-label="title">
            <button
                v-for="style in styles"
                :key="style.id"
                type="button"
                role="radio"
                :aria-checked="modelValue === style.id"
                class="flex min-w-[140px] flex-col items-center gap-3 rounded-2xl border-2 p-5 transition-all duration-200"
                :class="modelValue === style.id
                    ? 'border-primary bg-primary/5 shadow-md dark:border-primary dark:bg-primary/10'
                    : 'border-slate-200 hover:border-slate-300 dark:border-slate-800 dark:hover:border-slate-700'"
                @click="$emit('update:modelValue', style.id)"
            >
                <div class="flex items-center gap-1.5">
                    <div
                        class="flex h-14 w-10 flex-col gap-1 p-1.5"
                        :class="style.id === 'floating'
                            ? 'rounded-2xl border border-slate-300 dark:border-slate-600'
                            : 'rounded-lg border border-slate-300 dark:border-slate-600'"
                    >
                        <div class="h-1 w-6 rounded-full bg-primary/60"></div>
                        <div class="h-1 w-5 rounded-full bg-slate-300 dark:bg-slate-600"></div>
                        <div class="h-1 w-4 rounded-full bg-slate-300 dark:bg-slate-600"></div>
                        <div class="mt-auto h-1 w-3 rounded-full bg-slate-300 dark:bg-slate-600"></div>
                    </div>
                    <div class="flex h-14 w-14 flex-col gap-1 rounded-lg border border-slate-300 p-1.5 dark:border-slate-600">
                        <div class="h-1 w-10 rounded-full bg-slate-300 dark:bg-slate-600"></div>
                        <div class="h-1 w-8 rounded-full bg-slate-200 dark:bg-slate-700"></div>
                        <div class="mt-1 h-3 w-full rounded bg-slate-100 dark:bg-slate-800"></div>
                    </div>
                </div>

                <div class="text-center">
                    <h4 class="text-sm font-semibold text-slate-800 dark:text-slate-200">{{ style.label }}</h4>
                    <p class="text-[11px] text-slate-400 dark:text-slate-500">{{ style.description }}</p>
                </div>
            </button>
        </div>
    </PickerSection>
</template>
