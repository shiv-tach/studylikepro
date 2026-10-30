<script setup>
/**
 * The page's submit button. It keeps the label honest while the browser leaves
 * the page, which is also what stops a double tap from posting twice.
 */
import { computed } from 'vue';
import { useSurveyContext } from '../composables/useSurveyContext';
import { PRIMARY_BUTTON } from '../styles';

const props = defineProps({
    label: { type: String, default: '' },
    busyLabel: { type: String, default: null },
    formId: { type: String, default: null },
});

const { config, form } = useSurveyContext();

const busy = computed(() => form.busy.value);
const busyText = computed(() => props.busyLabel ?? config.copy.saving);
</script>

<template>
    <button type="submit" :form="formId ?? undefined" :disabled="busy" :class="PRIMARY_BUTTON">
        <span v-show="!busy" class="inline-flex items-center gap-2">
            {{ label }}
            <span aria-hidden="true">→</span>
        </span>

        <span v-show="busy" class="inline-flex items-center gap-2">
            <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24" aria-hidden="true">
                <circle class="opacity-25" cx="12" cy="12" r="9" stroke="currentColor" stroke-width="4" />
                <path class="opacity-90" fill="currentColor" d="M21 12a9 9 0 0 1-9 9v-4a5 5 0 0 0 5-5h4z" />
            </svg>
            {{ busyText }}
        </span>
    </button>
</template>
