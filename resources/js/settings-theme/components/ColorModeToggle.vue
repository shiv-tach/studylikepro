<script setup>
/**
 * Light / dark / system. The three options are a segmented radio group; the
 * icon key travels with each mode so the same component renders sun, moon or
 * monitor without a branch here.
 */
import AppIcon from '../../shared/components/AppIcon.vue';
import PickerSection from './PickerSection.vue';

defineProps({
    modes: { type: Array, default: () => [] },
    modelValue: { type: String, default: null },
    title: { type: String, required: true },
    hint: { type: String, default: '' },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <PickerSection :title="title" :hint="hint">
        <div class="inline-flex rounded-xl bg-slate-100 p-1 dark:bg-slate-800" role="radiogroup" :aria-label="title">
            <button
                v-for="mode in modes"
                :key="mode.id"
                type="button"
                role="radio"
                :aria-checked="modelValue === mode.id"
                class="flex items-center gap-2 rounded-lg px-5 py-2.5 text-sm font-medium transition-all duration-200"
                :class="modelValue === mode.id
                    ? 'bg-white text-slate-800 shadow-sm dark:bg-slate-700 dark:text-slate-100'
                    : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
                @click="$emit('update:modelValue', mode.id)"
            >
                <span class="h-4 w-4"><AppIcon :name="mode.icon" /></span>
                <span>{{ mode.label }}</span>
            </button>
        </div>
    </PickerSection>
</template>
