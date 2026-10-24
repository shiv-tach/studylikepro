<script setup>
/**
 * The teacher's earnings dashboard at /teacher/earnings. Blade decides every
 * amount, tab count, date and URL; this app only lays them out. The ledger's
 * paginator stays Blade-rendered under the mount, so every link still works
 * when the bundle has not loaded.
 */
import { provide } from 'vue';
import { THEME_PRESET_KEY, useThemePreset } from '../shared/composables/useThemePreset';
import RevealOnScroll from '../shared/components/RevealOnScroll.vue';
import { COPY_KEY, PAGE_KEY, URLS_KEY } from './composables/usePageContext';
import EarningsHero from './components/EarningsHero.vue';
import EarningsStatsGrid from './components/EarningsStatsGrid.vue';
import EarningsTrendCard from './components/EarningsTrendCard.vue';
import EarningStatusRail from './components/EarningStatusRail.vue';
import LedgerPanel from './components/LedgerPanel.vue';
import PayoutsPanel from './components/PayoutsPanel.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

// The shell owns the preset; sharing it here keeps every card in step.
provide(THEME_PRESET_KEY, useThemePreset(props.config.theme?.preset ?? 'classic'));
provide(COPY_KEY, props.config.copy ?? {});
provide(URLS_KEY, props.config.urls ?? {});
provide(PAGE_KEY, props.config);
</script>

<template>
    <div class="space-y-6">
        <RevealOnScroll>
            <EarningsHero />
        </RevealOnScroll>

        <RevealOnScroll :delay="20">
            <EarningsStatsGrid />
        </RevealOnScroll>

        <div class="grid items-stretch gap-4 lg:grid-cols-5">
            <RevealOnScroll :delay="30" class="h-full lg:col-span-3">
                <EarningsTrendCard class="h-full" />
            </RevealOnScroll>

            <RevealOnScroll :delay="40" class="h-full lg:col-span-2">
                <PayoutsPanel class="h-full" />
            </RevealOnScroll>
        </div>

        <!-- The rail is real links, so it belongs with the ledger it filters. -->
        <RevealOnScroll :delay="50">
            <EarningStatusRail />
        </RevealOnScroll>

        <RevealOnScroll :delay="60">
            <LedgerPanel />
        </RevealOnScroll>
    </div>
</template>
