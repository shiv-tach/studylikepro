<script setup>
/**
 * Question 6 — the institute of the highest qualification. The value stored on
 * the profile is the readable label, which is what the recap shows.
 */
import { useSurveyContext } from '../../composables/useSurveyContext';
import OptionCard from '../OptionCard.vue';
import FieldMessages from '../FieldMessages.vue';

const { config, form } = useSurveyContext();
</script>

<template>
    <fieldset>
        <legend class="sr-only">{{ config.title }}</legend>

        <div class="grid gap-3 sm:grid-cols-2">
            <OptionCard
                v-for="option in config.options.educationOptions"
                :key="option.value"
                v-model="form.values.education"
                name="education"
                :value="option.value"
                :icon="option.icon"
                :title="option.label"
                :hint="option.hint"
                required
            />
        </div>

        <FieldMessages :messages="form.errorsFor('education')" />
    </fieldset>
</template>
