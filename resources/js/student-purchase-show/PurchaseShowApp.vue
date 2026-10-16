<script setup>
/**
 * One purchase in detail: where it stands and what it costs, what is left of it,
 * whatever the teacher has delivered and the sessions booked against it. Blade
 * decides every one of those — the status, the price, the balance, the delivery
 * state and which action the student may take — and this app only composes the
 * panels that render them.
 */
import { provide } from 'vue';
import { THEME_PRESET_KEY, useThemePreset } from '../shared/composables/useThemePreset';
import RevealOnScroll from '../shared/components/RevealOnScroll.vue';
import UiAlert from '../shared/components/UiAlert.vue';
import { COPY_KEY, CSRF_KEY, URLS_KEY } from './composables/usePageContext';
import DeliveryPanel from './components/DeliveryPanel.vue';
import PlanBalancePanel from './components/PlanBalancePanel.vue';
import PurchaseStatusPanel from './components/PurchaseStatusPanel.vue';
import SessionList from './components/SessionList.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

// The Alpine shell owns the preset; sharing it here keeps every panel in step.
provide(THEME_PRESET_KEY, useThemePreset(props.config.theme?.preset ?? 'classic'));
provide(COPY_KEY, props.config.copy ?? {});
provide(URLS_KEY, props.config.urls ?? {});
provide(CSRF_KEY, props.config.csrfToken ?? '');
</script>

<template>
    <RevealOnScroll v-if="config.flash">
        <UiAlert :tone="config.flash.tone" :icon="config.flash.icon">
            {{ config.flash.message }}
        </UiAlert>
    </RevealOnScroll>

    <RevealOnScroll v-if="config.error">
        <UiAlert tone="rose" icon="alert" role="alert">{{ config.error }}</UiAlert>
    </RevealOnScroll>

    <RevealOnScroll v-if="config.status">
        <PurchaseStatusPanel :status="config.status" />
    </RevealOnScroll>

    <RevealOnScroll v-if="config.plan" :delay="20">
        <PlanBalancePanel :plan="config.plan" />
    </RevealOnScroll>

    <RevealOnScroll v-if="config.delivery" :delay="40">
        <DeliveryPanel :delivery="config.delivery" />
    </RevealOnScroll>

    <RevealOnScroll v-if="config.sessions" :delay="60">
        <SessionList :sessions="config.sessions" />
    </RevealOnScroll>
</template>
