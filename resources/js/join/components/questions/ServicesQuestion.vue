<script setup>
/**
 * Question 4 — the services the teacher would like to offer. The rates
 * question prices exactly this list (minus lessons, whose price is the hourly
 * fee).
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
                v-for="service in config.options.services"
                :key="service.value"
                v-model="form.values.services"
                type="checkbox"
                name="services[]"
                :value="service.value"
                :icon="service.icon"
                :title="service.label"
                :hint="service.hint"
            />
        </div>

        <FieldMessages :messages="form.errorsFor('services')" />
    </fieldset>
</template>
