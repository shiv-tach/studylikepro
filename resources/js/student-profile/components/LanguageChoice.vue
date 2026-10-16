<script setup>
/**
 * The medium of instruction. The marketplace config is the only list of student
 * languages that exists, so Blade sends it and this picker cannot offer one the
 * server would reject.
 */
import UiFormField from '../../shared/components/UiFormField.vue';
import { useCopy } from '../composables/usePageContext';
import { useProfileForm } from '../composables/useProfileForm';

const copy = useCopy();

const { values, errors, languages } = useProfileForm();

const OPTION_IDLE = 'border-slate-200/80 hover:border-primary/40 dark:border-slate-800 dark:hover:border-primary/30';
const OPTION_ACTIVE = 'border-primary bg-primary/5 ring-1 ring-primary/30';
</script>

<template>
    <UiFormField :label="copy.languageLabel" :errors="errors.learningLanguage" :hint="copy.languageHint">
        <div class="mt-1 grid gap-2">
            <label
                v-for="language in languages"
                :key="language.value"
                class="flex cursor-pointer items-center gap-3 rounded-xl border px-4 py-2.5 transition-all duration-300"
                :class="values.learningLanguage === language.value ? OPTION_ACTIVE : OPTION_IDLE"
            >
                <input
                    v-model="values.learningLanguage"
                    type="radio"
                    name="learning_language"
                    :value="language.value"
                    class="h-4 w-4 shrink-0 border-slate-300 text-primary focus:ring-primary dark:border-slate-600 dark:bg-slate-900"
                >
                <span class="text-lg" aria-hidden="true">{{ language.icon }}</span>
                <span class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ language.value }}</span>
            </label>
        </div>
    </UiFormField>
</template>
