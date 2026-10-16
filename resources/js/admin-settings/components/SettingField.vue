<script setup>
/**
 * One platform setting: its label, the control (a textarea for free text, a
 * number input otherwise), the description and Laravel's validation messages.
 * The control's `name` is the setting key, so the form posts exactly what the
 * server validates.
 */
import { computed, inject } from 'vue';
import UiFormField from '../../shared/components/UiFormField.vue';
import { SETTINGS_FORM_KEY } from '../composables/useSettingsForm';

const props = defineProps({
    setting: { type: Object, required: true },
    errors: { type: Array, default: () => [] },
    currencySymbol: { type: String, default: '' },
});

const form = inject(SETTINGS_FORM_KEY);
const inputId = computed(() => `setting-${props.setting.key}`);

// Money is entered in whole currency units, so the hint names the currency.
const hint = computed(() =>
    props.setting.type === 'money' && props.currencySymbol
        ? `${props.setting.description} · ${props.currencySymbol}`
        : props.setting.description,
);

const controlClasses = 'mt-1 block w-full rounded-xl border-slate-300 bg-white text-sm shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-200';
</script>

<template>
    <UiFormField :label="setting.label" :for-id="inputId" :hint="hint" :errors="errors">
        <textarea
            v-if="setting.type === 'text'"
            :id="inputId"
            v-model="form.values[setting.key]"
            :name="setting.key"
            rows="3"
            :class="controlClasses"
        ></textarea>

        <input
            v-else
            :id="inputId"
            v-model="form.values[setting.key]"
            :name="setting.key"
            :type="setting.inputType"
            :step="setting.step"
            :class="controlClasses"
        >
    </UiFormField>
</template>
