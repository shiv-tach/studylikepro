<script setup>
/**
 * The teacher's reviews page at /teacher/reviews. Blade decides every number,
 * label, date and URL; this app only lays them out. Reporting a review is still
 * a real POST form inside a dialog, so the action works the same when the bundle
 * is slow to load, and a rejected submit reopens the dialog it came from.
 */
import { computed, provide, ref } from 'vue';
import { THEME_PRESET_KEY, useThemePreset } from '../shared/composables/useThemePreset';
import { useNotices } from '../shared/composables/useNotices';
import UiToastStack from '../shared/components/UiToastStack.vue';
import { COPY_KEY, CSRF_KEY, PAGE_KEY, URLS_KEY } from './composables/usePageContext';
import RatingBreakdownCard from './components/RatingBreakdownCard.vue';
import FlagReviewDialog from './components/FlagReviewDialog.vue';
import ReviewStatsGrid from './components/ReviewStatsGrid.vue';
import ReviewsHero from './components/ReviewsHero.vue';
import ReviewList from './components/ReviewList.vue';

const props = defineProps({
    config: { type: Object, required: true },
});

// The shell owns the preset; sharing it here keeps every card in step.
provide(THEME_PRESET_KEY, useThemePreset(props.config.theme?.preset ?? 'classic'));
provide(COPY_KEY, props.config.copy ?? {});
provide(URLS_KEY, props.config.urls ?? {});
provide(CSRF_KEY, props.config.csrfToken ?? '');
provide(PAGE_KEY, props.config);

const copy = computed(() => props.config.copy ?? {});

const { toasts, dismiss } = useNotices(props.config.notices ?? []);

// A rejected report carries the review it came from, so the same dialog opens
// again with the typed reason and notes already filled in.
const replayedReview = props.config.reviews.find(
    (review) => review.id === props.config.flag?.openId,
);

const flagTarget = ref(replayedReview ?? null);

function openFlag(review) {
    if (!review?.flagUrl) {
        return;
    }

    flagTarget.value = review;
}

function closeFlag() {
    flagTarget.value = null;
}
</script>

<template>
    <div class="space-y-6">
        <UiToastStack
            :toasts="toasts"
            :label="copy.toast?.label ?? 'Notifications'"
            :dismiss-label="copy.toast?.dismiss ?? 'Dismiss'"
            @dismiss="dismiss"
        />

        <ReviewsHero />

        <ReviewStatsGrid />

        <RatingBreakdownCard />

        <ReviewList :reviews="config.reviews" @report="openFlag" />

        <FlagReviewDialog :review="flagTarget" @close="closeFlag" />
    </div>
</template>
