<script setup>
/**
 * Question 1 — O/L, A/L or both. One card per level, read from the seeded
 * catalog so the grade span cannot drift from the picker.
 */
import { useSurveyContext } from '../../composables/useSurveyContext';
import OptionCard from '../OptionCard.vue';
import FieldMessages from '../FieldMessages.vue';

const { config, form } = useSurveyContext();
</script>

<template>
    <fieldset>
        <legend class="sr-only">{{ config.title }}</legend>

        <div class="grid gap-3 sm:grid-cols-3">
            <OptionCard
                v-for="option in config.options.levels"
                :key="option.value"
                v-model="form.values.level"
                name="level"
                :value="option.value"
                :icon="option.icon"
                :title="option.label"
                :hint="option.hint"
                required
            />
        </div>

        <FieldMessages :messages="form.errorsFor('level')" />
    </fieldset>
</template>
