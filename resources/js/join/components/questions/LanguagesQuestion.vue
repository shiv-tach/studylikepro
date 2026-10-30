<script setup>
/**
 * Question 3 — the languages a lesson can be taught in. More than one is fine.
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
                v-for="language in config.options.languages"
                :key="language"
                v-model="form.values.languages"
                type="checkbox"
                name="languages[]"
                :value="language"
                :title="language"
                align="center"
            />
        </div>

        <FieldMessages :messages="form.errorsFor('languages')" />
    </fieldset>
</template>
