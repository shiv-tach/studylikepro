<script setup>
import { computed, provide, ref } from 'vue';
import { THEME_PRESET_KEY, useThemePreset } from '../shared/composables/useThemePreset';
import AppIcon from '../shared/components/AppIcon.vue';
import RevealOnScroll from '../shared/components/RevealOnScroll.vue';
import UiEmptyState from '../shared/components/UiEmptyState.vue';
import { COPY_KEY, URLS_KEY } from './composables/usePageContext';
import RequestCard from './components/RequestCard.vue';
import RequestFilters from './components/RequestFilters.vue';
import RequestsEmptyState from './components/RequestsEmptyState.vue';
import RequestsOverview from './components/RequestsOverview.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

// The Alpine shell owns the preset; sharing it here keeps every card in step.
provide(THEME_PRESET_KEY, useThemePreset(props.config.theme?.preset ?? 'classic'));
provide(COPY_KEY, props.config.copy ?? {});
provide(URLS_KEY, props.config.urls ?? {});

const requests = computed(() => props.config.requests ?? []);
const filters = computed(() => props.config.filters ?? []);
const activeFilter = ref('all');

// Filtering happens over the ten requests this page already holds, so flipping
// between the buckets never costs a round trip; paging stays server-rendered.
const visibleRequests = computed(() => (activeFilter.value === 'all'
    ? requests.value
    : requests.value.filter((request) => request.group === activeFilter.value)));
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

    <RequestsEmptyState v-if="!requests.length" />

    <template v-else>
        <RevealOnScroll>
            <RequestsOverview :stats="config.stats" />
        </RevealOnScroll>

        <RevealOnScroll :delay="20">
            <RequestFilters :filters="filters" :active="activeFilter" @select="activeFilter = $event" />
        </RevealOnScroll>

        <TransitionGroup
            v-if="visibleRequests.length"
            tag="div"
            name="request-list"
            class="space-y-4"
        >
            <RequestCard v-for="request in visibleRequests" :key="request.id" :request="request" />
        </TransitionGroup>

        <RevealOnScroll v-else :delay="20">
            <UiEmptyState icon="clipboard" :message="config.copy.filterEmpty" />
        </RevealOnScroll>
    </template>
</template>

<style scoped>
/* Filtering swaps whole cards, so they slide rather than blink out. */
.request-list-move,
.request-list-enter-active,
.request-list-leave-active {
    transition: opacity 250ms ease, transform 250ms ease;
}

.request-list-enter-from,
.request-list-leave-to {
    opacity: 0;
    transform: translateY(6px);
}

.request-list-leave-active {
    position: absolute;
    width: 100%;
}

@media (prefers-reduced-motion: reduce) {
    .request-list-move,
    .request-list-enter-active,
    .request-list-leave-active {
        transition: none;
    }
}
</style>
