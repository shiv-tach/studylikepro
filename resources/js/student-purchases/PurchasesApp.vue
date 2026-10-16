<script setup>
/**
 * "My purchases": the wallet summary, the Active/Past tabs, the service-type
 * filter and one card per purchase. The filters are real links and the figures
 * come from Blade, so this app only renders the island — every query, price and
 * permission check stays server-side.
 */
import { provide } from 'vue';
import { THEME_PRESET_KEY, useThemePreset } from '../shared/composables/useThemePreset';
import AppIcon from '../shared/components/AppIcon.vue';
import RevealOnScroll from '../shared/components/RevealOnScroll.vue';
import { COPY_KEY, CSRF_KEY, URLS_KEY } from './composables/usePageContext';
import PurchaseCard from './components/PurchaseCard.vue';
import PurchaseFilters from './components/PurchaseFilters.vue';
import PurchasesEmptyState from './components/PurchasesEmptyState.vue';
import PurchasesOverview from './components/PurchasesOverview.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

// The Alpine shell owns the preset; sharing it here keeps every card in step.
provide(THEME_PRESET_KEY, useThemePreset(props.config.theme?.preset ?? 'classic'));
provide(COPY_KEY, props.config.copy ?? {});
provide(URLS_KEY, props.config.urls ?? {});
provide(CSRF_KEY, props.config.csrfToken ?? '');
</script>

<template>
    <RevealOnScroll v-if="config.flash?.cancelled">
        <div
            class="flex items-start gap-3 rounded-2xl border border-emerald-200/80 bg-emerald-50 p-4 text-sm text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300"
            role="status"
        >
            <span class="mt-0.5 h-4 w-4 shrink-0"><AppIcon name="check-circle" /></span>
            <span>{{ config.copy.cancelled }}</span>
        </div>
    </RevealOnScroll>

    <RevealOnScroll>
        <PurchasesOverview :stats="config.stats" />
    </RevealOnScroll>

    <RevealOnScroll :delay="20">
        <PurchaseFilters
            :tabs="config.tabs"
            :active-tab="config.tab"
            :type-options="config.typeOptions"
            :active-type="config.selectedType"
        />
    </RevealOnScroll>

    <div v-if="config.purchases.length" class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
        <RevealOnScroll
            v-for="(purchase, index) in config.purchases"
            :key="purchase.id"
            :delay="Math.min(index, 4) * 20"
            class="h-full"
        >
            <PurchaseCard :purchase="purchase" />
        </RevealOnScroll>
    </div>

    <RevealOnScroll v-else :delay="20">
        <PurchasesEmptyState :filtered="config.filtered" />
    </RevealOnScroll>
</template>
