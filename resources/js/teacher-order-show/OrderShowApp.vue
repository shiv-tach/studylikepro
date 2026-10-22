<script setup>
/**
 * One order in detail. Blade decides the status, the money, the balance, the
 * session rows, the marking state and every route; this app only composes the
 * panels that render them. The main column is "what is happening with the
 * work", the aside is "who bought it and what it pays".
 */
import { provide } from 'vue';
import { THEME_PRESET_KEY, useThemePreset } from '../shared/composables/useThemePreset';
import RevealOnScroll from '../shared/components/RevealOnScroll.vue';
import { COPY_KEY, PAGE_KEY, URLS_KEY } from './composables/usePageContext';
import MarkingPanel from './components/MarkingPanel.vue';
import OfferingPanel from './components/OfferingPanel.vue';
import OrderHero from './components/OrderHero.vue';
import OrderMoneyPanel from './components/OrderMoneyPanel.vue';
import OrderTimelinePanel from './components/OrderTimelinePanel.vue';
import PlanBalancePanel from './components/PlanBalancePanel.vue';
import SessionsPanel from './components/SessionsPanel.vue';
import StudentPanel from './components/StudentPanel.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

// The shell owns the preset; sharing it here keeps every panel in step.
provide(THEME_PRESET_KEY, useThemePreset(props.config.theme?.preset ?? 'classic'));
provide(COPY_KEY, props.config.copy ?? {});
provide(URLS_KEY, props.config.urls ?? {});
provide(PAGE_KEY, props.config);
</script>

<template>
    <div class="space-y-6">
        <RevealOnScroll>
            <OrderHero />
        </RevealOnScroll>

        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <RevealOnScroll :delay="20">
                    <OrderTimelinePanel :steps="config.timeline" />
                </RevealOnScroll>

                <RevealOnScroll v-if="config.plan" :delay="40">
                    <PlanBalancePanel :plan="config.plan" />
                </RevealOnScroll>

                <RevealOnScroll :delay="60">
                    <SessionsPanel :sessions="config.sessions" />
                </RevealOnScroll>

                <RevealOnScroll v-if="config.marking" :delay="80">
                    <MarkingPanel :marking="config.marking" />
                </RevealOnScroll>
            </div>

            <div class="space-y-6">
                <RevealOnScroll :delay="20">
                    <OrderMoneyPanel :money="config.money" />
                </RevealOnScroll>

                <RevealOnScroll :delay="40">
                    <StudentPanel :student="config.student" />
                </RevealOnScroll>

                <RevealOnScroll :delay="60">
                    <OfferingPanel :offering="config.offering" />
                </RevealOnScroll>
            </div>
        </div>
    </div>
</template>
