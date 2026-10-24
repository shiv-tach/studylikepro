<script setup>
/**
 * The current page of orders, newest first — the order the controller paginates
 * in. Pagination itself stays Blade-rendered below the mount.
 */
import RevealOnScroll from '../../shared/components/RevealOnScroll.vue';
import MarkingOrderCard from './MarkingOrderCard.vue';
import MarkingEmptyState from './MarkingEmptyState.vue';

defineProps({
    orders: { type: Array, default: () => [] },
});
</script>

<template>
    <div v-if="orders.length" class="space-y-3">
        <RevealOnScroll
            v-for="(order, index) in orders"
            :key="order.id"
            :delay="Math.min(index, 4) * 20"
        >
            <MarkingOrderCard :order="order" />
        </RevealOnScroll>
    </div>

    <MarkingEmptyState v-else />
</template>
