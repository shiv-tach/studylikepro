<script setup>
/**
 * Question 7 — the hourly fee plus a price for each service picked in question
 * 4. The server filters that list, so a tampered field cannot price a service
 * the teacher never offered.
 */
import { useSurveyContext } from '../../composables/useSurveyContext';
import FieldMessages from '../FieldMessages.vue';
import { format } from '../../format';
import { LABEL, NUMBER_FIELD } from '../../styles';

const { config, form } = useSurveyContext();

const SERVICE_RATE_FIELD = 'block w-full rounded-xl border-slate-300 bg-white text-base font-semibold text-slate-900 shadow-sm focus:border-primary focus:ring-primary dark:border-slate-700 dark:bg-slate-900 dark:text-slate-100';
</script>

<template>
    <div class="space-y-4">
        <div class="rounded-xl border border-primary/25 bg-primary/5 p-4 dark:border-primary/30 dark:bg-primary/10">
            <label for="hourly_rate" :class="LABEL">{{ config.questionCopy.hourlyRate }}</label>

            <div class="mt-2 flex items-center gap-2">
                <span class="shrink-0 text-sm font-semibold text-slate-500 dark:text-slate-400">{{ config.currency.symbol }}</span>
                <input
                    id="hourly_rate"
                    v-model="form.values.hourly_rate"
                    name="hourly_rate"
                    type="number"
                    inputmode="numeric"
                    :min="config.limits.rateMin"
                    :max="config.limits.rateMax"
                    step="1"
                    required
                    :class="NUMBER_FIELD"
                />
                <span class="shrink-0 text-sm font-semibold text-slate-500 dark:text-slate-400">/ {{ config.questionCopy.hourUnit }}</span>
            </div>

            <p class="mt-1 text-xs text-slate-500 dark:text-slate-400">{{ config.questionCopy.hourlyRateHint }}</p>

            <FieldMessages :messages="form.errorsFor('hourly_rate')" />
        </div>

        <div
            v-for="service in config.options.pricedServices"
            :key="service.value"
            class="rounded-xl border border-slate-200 bg-slate-50/60 p-4 dark:border-slate-800 dark:bg-slate-800/40"
        >
            <label :for="`service_rates_${service.value}`" class="flex items-center gap-2 text-sm font-semibold text-slate-700 dark:text-slate-300">
                <span aria-hidden="true">{{ service.icon }}</span>
                {{ format(config.questionCopy.serviceRate, { service: service.label }) }}
            </label>
            <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">{{ service.hint }}</p>

            <div class="mt-2 flex items-center gap-2">
                <span class="shrink-0 text-sm font-semibold text-slate-500 dark:text-slate-400">{{ config.currency.symbol }}</span>
                <input
                    :id="`service_rates_${service.value}`"
                    v-model="form.values.service_rates[service.value]"
                    :name="`service_rates[${service.value}]`"
                    type="number"
                    inputmode="numeric"
                    :min="config.limits.rateMin"
                    :max="config.limits.rateMax"
                    step="1"
                    required
                    :class="SERVICE_RATE_FIELD"
                />
            </div>

            <FieldMessages :messages="form.errorsUnder(`service_rates.${service.value}`)" />
        </div>

        <p v-if="config.options.pricedServices.length === 0" class="rounded-xl bg-slate-50 p-3 text-xs text-slate-500 dark:bg-slate-800/60 dark:text-slate-400">
            {{ config.questionCopy.lessonsOnly }}
        </p>
    </div>
</template>
