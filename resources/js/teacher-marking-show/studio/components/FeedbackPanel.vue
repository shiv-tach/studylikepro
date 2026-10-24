<script setup>
/**
 * The student-facing summary. One real textarea inside the delivery form, so
 * the text posts with the marked files without a second round trip.
 */
import { usePanel } from '../../../shared/composables/usePanel';
import { useCopy } from '../../composables/usePageContext';

defineProps({
    modelValue: { type: String, default: '' },
    copy: { type: Object, default: () => ({}) },
    max: { type: Number, default: 5000 },
});

const emit = defineEmits(['update:modelValue']);
const panel = usePanel();
const copyAll = useCopy();
</script>

<template>
    <section :class="panel" aria-labelledby="studio-feedback-heading">
        <h2 id="studio-feedback-heading" class="text-sm font-bold text-slate-800 dark:text-slate-100">
            {{ copy.title ?? copyAll.studio?.feedback?.title }}
        </h2>
        <p class="mt-0.5 text-[11px] text-slate-500 dark:text-slate-400">
            {{ (copy.hint ?? copyAll.studio?.feedback?.hint ?? '').replace(':count', max) }}
        </p>

        <textarea
            :value="modelValue"
            name="feedback"
            rows="5"
            :maxlength="max"
            :placeholder="copy.placeholder ?? copyAll.studio?.feedback?.placeholder"
            class="mt-2 block w-full rounded-xl border-slate-300 bg-white text-xs shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
            @input="emit('update:modelValue', $event.target.value)"
        ></textarea>

        <p class="mt-1 text-right text-[10px] font-semibold text-slate-400">{{ modelValue.length }} / {{ max }}</p>
    </section>
</template>
