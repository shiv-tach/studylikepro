<script setup>
/**
 * The teacher's order book at /teacher/orders. Blade decides every status
 * count, price, label and filter URL; this app only lays them out — the status
 * rail is real links and the search is a real GET form, so filtering works
 * exactly the same with JavaScript switched off.
 */
import { computed, provide } from 'vue';
import { THEME_PRESET_KEY, useThemePreset } from '../shared/composables/useThemePreset';
import RevealOnScroll from '../shared/components/RevealOnScroll.vue';
import { COPY_KEY, PAGE_KEY, URLS_KEY } from './composables/usePageContext';
import { useViewMode } from './composables/useViewMode';
import OrderCard from './components/OrderCard.vue';
import OrderStatusNav from './components/OrderStatusNav.vue';
import OrderToolbar from './components/OrderToolbar.vue';
import OrdersEmptyState from './components/OrdersEmptyState.vue';
import OrdersHero from './components/OrdersHero.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

// The shell owns the preset; sharing it here keeps every card in step.
provide(THEME_PRESET_KEY, useThemePreset(props.config.theme?.preset ?? 'classic'));
provide(COPY_KEY, props.config.copy ?? {});
provide(URLS_KEY, props.config.urls ?? {});
provide(PAGE_KEY, props.config);

const copy = computed(() => props.config.copy ?? {});

// The book opens as a list; the toolbar's toggle remembers a teacher's pick.
const view = useViewMode();
</script>

<template>
    <div class="space-y-6">
        <RevealOnScroll>
            <OrdersHero />
        </RevealOnScroll>

        <RevealOnScroll :delay="20">
            <OrderStatusNav :tabs="config.statusTabs" :label="copy.tabs?.label" />
        </RevealOnScroll>

        <RevealOnScroll :delay="30">
            <OrderToolbar
                v-model:view="view"
                :search="config.search"
                :visible="config.pagination?.visible ?? config.purchases.length"
                :total="config.pagination?.total ?? config.purchases.length"
                :filtered="config.filtered"
            />
        </RevealOnScroll>

        <div v-if="config.purchases.length" :class="view === 'card' ? 'grid gap-3 xl:grid-cols-2' : 'flex flex-col gap-3'">
            <RevealOnScroll
                v-for="(order, index) in config.purchases"
                :key="order.id"
                :delay="Math.min(index, 4) * 20"
                :class="view === 'card' ? 'h-full' : ''"
            >
                <OrderCard :order="order" :view="view" />
            </RevealOnScroll>
        </div>

        <RevealOnScroll v-else :delay="40">
            <OrdersEmptyState :filtered="config.filtered" />
        </RevealOnScroll>
    </div>
</template>
