<script setup>
/**
 * The ranked "Do next" queue for one lesson. Blade decides the order and the
 * legal actions; this component only lays the rows out and forwards a dialog
 * action (cancel) to the page.
 */
import { computed } from 'vue';
import RevealOnScroll from '../../shared/components/RevealOnScroll.vue';
import UiSectionHeading from '../../shared/components/UiSectionHeading.vue';
import UiEmptyState from '../../shared/components/UiEmptyState.vue';
import { useCopy } from '../composables/usePageContext';
import PriorityItem from './PriorityItem.vue';

defineProps({
    items: { type: Array, default: () => [] },
});

const emit = defineEmits(['cancel']);

const copy = useCopy();

const emptyMessage = computed(() => [
    copy.priority?.emptyTitle,
    copy.priority?.emptyBody,
].filter(Boolean).join(' '));
</script>

<template>
    <section class="space-y-4" aria-labelledby="booking-priority-title">
        <UiSectionHeading
            id="booking-priority-title"
            :title="copy.priority?.title"
            :description="copy.priority?.description"
            icon="list"
        />

        <ol v-if="items.length" class="space-y-3">
            <RevealOnScroll v-for="(item, index) in items" :key="item.kind" :delay="Math.min(index, 4) * 20">
                <PriorityItem :item="item" :step="index + 1" @cancel="emit('cancel')" />
            </RevealOnScroll>
        </ol>

        <UiEmptyState v-else icon="check-circle" :message="emptyMessage" />
    </section>
</template>
