<script setup>
/**
 * Question 4 — the services the teacher would like to offer, each one priced on
 * the spot. Asking for the prices a few screens later let a teacher pick a
 * service and never price it, so the price field now lives on the service card
 * and appears the moment the service is ticked.
 *
 * Lessons are priced by the hourly fee (`hourly_rate`); every other service
 * posts its own amount under `service_rates[<type>]`, which is the shape the
 * controller and `InviteOnboardingService` already expect.
 */
import { computed } from 'vue';
import { useSurveyContext } from '../../composables/useSurveyContext';
import FieldMessages from '../FieldMessages.vue';
import ServicePriceCard from './ServicePriceCard.vue';
import { GHOST_BUTTON } from '../../styles';
import { format } from '../../format';

const { config, form } = useSurveyContext();

const LESSON = 'lesson';

const copy = computed(() => config.questionCopy ?? {});
const services = computed(() => config.options.services ?? []);
const picked = computed(() => form.values.services ?? []);
const allPicked = computed(
    () => services.value.length > 0 && picked.value.length === services.value.length,
);

function priceFor(service) {
    return service.value === LESSON
        ? form.values.hourly_rate
        : form.values.service_rates[service.value];
}

function setPrice(service, value) {
    if (service.value === LESSON) {
        form.values.hourly_rate = value;

        return;
    }

    form.values.service_rates[service.value] = value;
}

function messagesFor(service) {
    return service.value === LESSON
        ? form.errorsFor('hourly_rate')
        : form.errorsUnder(`service_rates.${service.value}`);
}

function selectAll() {
    form.values.services = services.value.map((service) => service.value);
}

function clearAll() {
    form.values.services = [];
}
</script>

<template>
    <fieldset class="space-y-4">
        <legend class="sr-only">{{ config.title }}</legend>

        <div class="flex flex-wrap items-center justify-between gap-2">
            <p class="text-xs font-semibold text-slate-500 dark:text-slate-400">
                {{ picked.length === 0
                    ? copy.servicePickedNone
                    : format(copy.servicePicked, { count: picked.length, total: services.length }) }}
            </p>

            <div class="flex items-center gap-1">
                <button type="button" :class="GHOST_BUTTON" :disabled="allPicked" @click="selectAll">
                    {{ copy.serviceSelectAll }}
                </button>
                <button type="button" :class="GHOST_BUTTON" :disabled="picked.length === 0" @click="clearAll">
                    {{ copy.serviceClear }}
                </button>
            </div>
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <ServicePriceCard
                v-for="service in services"
                :key="service.value"
                v-model="form.values.services"
                :price="priceFor(service)"
                :service="service"
                :currency="config.currency.symbol"
                :min="config.limits.rateMin"
                :max="config.limits.rateMax"
                :unit-label="service.unit === 'hour' ? copy.hourUnit : null"
                :hint="copy.servicePriceHint"
                :messages="messagesFor(service)"
                @update:price="setPrice(service, $event)"
            />
        </div>

        <FieldMessages :messages="form.errorsFor('services')" />
    </fieldset>
</template>
