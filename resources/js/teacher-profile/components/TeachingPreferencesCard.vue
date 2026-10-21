<script setup>
/**
 * The languages the teacher runs lessons in and the rate students see. The
 * language list is the marketplace config Blade sends — the picker cannot offer
 * one the request would reject — and the rate hint repeats the exact amount the
 * preview card is showing.
 */
import { computed } from 'vue';
import { usePanel } from '../../shared/composables/usePanel';
import AppIcon from '../../shared/components/AppIcon.vue';
import UiFormField from '../../shared/components/UiFormField.vue';
import UiSectionHeading from '../../shared/components/UiSectionHeading.vue';
import { format, useCopy } from '../composables/usePageContext';
import { useProfileForm } from '../composables/useProfileForm';
import { FIELD, OPTION_ACTIVE, OPTION_IDLE } from '../styles';

const copy = useCopy();
const panel = usePanel();

const { values, errors, languages, limits, currencySymbol, formattedRate, toggleLanguage } = useProfileForm();

const rateHint = computed(() => (formattedRate.value
    ? format(copy.rateHint, { amount: formattedRate.value })
    : copy.rateHintEmpty));
</script>

<template>
    <section :class="panel">
        <UiSectionHeading :title="copy.preferencesTitle" :description="copy.preferencesHint" icon="book" level="h3" />

        <div class="mt-5">
            <UiFormField :label="copy.languagesLabel" :errors="errors.languages" :hint="copy.languagesHint">
                <div class="mt-1 grid grid-cols-2 gap-2 sm:grid-cols-3">
                    <label
                        v-for="language in languages"
                        :key="language"
                        class="flex cursor-pointer items-center gap-2 rounded-xl border px-3 py-2 text-sm text-slate-700 transition-all duration-300 dark:text-slate-300"
                        :class="values.languages.includes(language) ? OPTION_ACTIVE : OPTION_IDLE"
                    >
                        <input
                            type="checkbox"
                            name="languages[]"
                            :value="language"
                            :checked="values.languages.includes(language)"
                            class="h-4 w-4 shrink-0 rounded border-slate-300 text-primary focus:ring-primary dark:border-slate-600 dark:bg-slate-900"
                            @change="toggleLanguage(language)"
                        >
                        {{ language }}
                    </label>
                </div>
            </UiFormField>
        </div>

        <div class="mt-5">
            <UiFormField :label="copy.rateLabel" for-id="hourly_rate" required :errors="errors.hourlyRate">
                <div class="relative mt-1">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-slate-400">
                        {{ currencySymbol }}
                    </span>
                    <input
                        id="hourly_rate"
                        v-model="values.hourlyRate"
                        name="hourly_rate"
                        type="number"
                        :min="limits.minRate"
                        :max="limits.maxRate"
                        step="1"
                        :class="[FIELD, 'pl-10']"
                    >
                </div>

                <p class="mt-2 flex items-center gap-1.5 text-xs text-slate-400">
                    <span class="h-3.5 w-3.5 shrink-0"><AppIcon name="tag" /></span>
                    {{ rateHint }}
                </p>
            </UiFormField>
        </div>
    </section>
</template>
