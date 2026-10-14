<script setup>
/**
 * The optional per-lesson budget. The currency symbol is a platform setting, so
 * it is printed from the island instead of being assumed here.
 */
import { ref } from 'vue';
import FieldMessages from './FieldMessages.vue';
import FormPanel from './FormPanel.vue';
import { useCopy } from '../composables/usePageContext';

const props = defineProps({
    value: { type: [String, Number], default: null },
    symbol: { type: String, default: '' },
    messages: { type: Array, default: () => [] },
});

const copy = useCopy();
const budget = ref(props.value ?? '');
</script>

<template>
    <FormPanel :title="copy.budgetTitle" :badge="copy.optional" :description="copy.budgetHint">
        <div class="mt-4 flex flex-wrap items-center gap-3">
            <div class="flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-3 py-2 shadow-sm dark:border-slate-700 dark:bg-slate-900">
                <span aria-hidden="true" class="text-sm font-semibold text-slate-400">{{ symbol }}</span>
                <input
                    id="request-budget"
                    v-model="budget"
                    name="budget"
                    type="number"
                    step="0.01"
                    min="0"
                    max="100000"
                    :placeholder="copy.budgetPlaceholder"
                    class="w-28 border-0 bg-transparent p-0 text-sm text-slate-800 focus:ring-0 dark:text-slate-200"
                />
            </div>
            <label for="request-budget" class="text-xs text-slate-400">{{ copy.budgetPerLesson }}</label>
        </div>

        <FieldMessages :messages="messages" />
    </FormPanel>
</template>
