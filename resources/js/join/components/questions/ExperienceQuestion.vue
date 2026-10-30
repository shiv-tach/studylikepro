<script setup>
/**
 * Question 5 — years of teaching. Whole years; 0 is a valid answer for a
 * teacher who has not taught yet.
 */
import { useSurveyContext } from '../../composables/useSurveyContext';
import FieldMessages from '../FieldMessages.vue';
import { LABEL, NUMBER_FIELD } from '../../styles';

const { config, form } = useSurveyContext();
</script>

<template>
    <div class="rounded-xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-800 dark:bg-slate-800/40">
        <label for="experience_years" :class="LABEL">{{ config.questionCopy.years }}</label>

        <div class="mt-2 flex items-center gap-2">
            <input
                id="experience_years"
                v-model="form.values.experience_years"
                name="experience_years"
                type="number"
                inputmode="numeric"
                :min="config.limits.experienceMin"
                :max="config.limits.experienceMax"
                step="1"
                required
                :class="NUMBER_FIELD"
            />
            <span class="shrink-0 text-sm font-semibold text-slate-500 dark:text-slate-400">{{ config.questionCopy.yearsUnit }}</span>
        </div>

        <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ config.questionCopy.yearsHint }}</p>

        <FieldMessages :messages="form.errorsFor('experience_years')" />
    </div>
</template>
