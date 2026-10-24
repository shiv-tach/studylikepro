<script setup>
/**
 * The optional mark rows. Names stay positional (`marks[0][label]`), exactly what
 * `MarkPaperRequest` validates, and a rejected submit replays every row the
 * teacher had typed.
 */
import AppIcon from '../../shared/components/AppIcon.vue';
import { useCopy } from '../composables/usePageContext';

defineProps({
    rows: { type: Array, default: () => [] },
    errors: { type: Array, default: () => [] },
    max: { type: Number, default: 50 },
});

const emit = defineEmits(['add', 'remove']);

const copy = useCopy();
</script>

<template>
    <div>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ copy.form?.marksLabel }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ copy.form?.marksHint }}</p>
            </div>

            <button
                type="button"
                class="inline-flex items-center gap-1 text-xs font-semibold text-primary transition-colors hover:text-primary/80 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="rows.length >= max"
                @click="emit('add')"
            >
                <span class="h-3.5 w-3.5" aria-hidden="true"><AppIcon name="plus" /></span>
                {{ copy.form?.addRow }}
            </button>
        </div>

        <div class="mt-2 space-y-2">
            <div
                v-for="(row, index) in rows"
                :key="index"
                class="flex items-center gap-2"
            >
                <label :for="`mark-label-${index}`" class="sr-only">{{ copy.form?.labelPlaceholder }}</label>
                <input
                    :id="`mark-label-${index}`"
                    v-model="row.label"
                    type="text"
                    :name="`marks[${index}][label]`"
                    maxlength="120"
                    :placeholder="copy.form?.labelPlaceholder"
                    class="block w-2/5 rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                >

                <label :for="`mark-value-${index}`" class="sr-only">{{ copy.form?.valuePlaceholder }}</label>
                <input
                    :id="`mark-value-${index}`"
                    v-model="row.value"
                    type="text"
                    :name="`marks[${index}][value]`"
                    maxlength="120"
                    :placeholder="copy.form?.valuePlaceholder"
                    class="block w-2/5 rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200"
                >

                <button
                    type="button"
                    class="ml-auto rounded-lg p-1.5 text-slate-400 transition-colors hover:text-rose-500"
                    :aria-label="copy.form?.removeRow"
                    @click="emit('remove', index)"
                >
                    <span class="block h-4 w-4"><AppIcon name="close" /></span>
                </button>
            </div>
        </div>

        <ul v-if="errors.length" class="mt-2 space-y-1" role="alert">
            <li
                v-for="(message, index) in errors"
                :key="index"
                class="text-sm font-medium text-rose-600 dark:text-rose-400"
            >
                {{ message }}
            </li>
        </ul>
    </div>
</template>
