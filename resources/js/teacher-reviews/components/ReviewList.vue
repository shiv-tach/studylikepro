<script setup>
/**
 * The current page of visible reviews, newest first — the order the controller
 * paginates in. Pagination itself stays Blade-rendered below the mount.
 */
import RevealOnScroll from '../../shared/components/RevealOnScroll.vue';
import ReviewCard from './ReviewCard.vue';
import ReviewsEmptyState from './ReviewsEmptyState.vue';

defineProps({
    reviews: { type: Array, default: () => [] },
});

const emit = defineEmits(['report']);
</script>

<template>
    <div v-if="reviews.length" class="space-y-3">
        <RevealOnScroll
            v-for="(review, index) in reviews"
            :key="review.id"
            :delay="Math.min(index, 4) * 20"
        >
            <ReviewCard :review="review" @report="emit('report', $event)" />
        </RevealOnScroll>
    </div>

    <ReviewsEmptyState v-else />
</template>
