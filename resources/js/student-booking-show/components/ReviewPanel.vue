<script setup>
/**
 * The student's own review of a delivered lesson. Blade decides whether the
 * lesson is finished, whether a review exists, whether it is still editable and
 * which action to offer, so this panel only draws the verdict and the one link.
 */
import { computed } from 'vue';
import { usePanel } from '../../shared/composables/usePanel';

const props = defineProps({
    review: { type: Object, required: true },
});

const panel = usePanel();

const stars = computed(() => (props.review.rating
    ? { filled: '★'.repeat(props.review.rating), empty: '★'.repeat(Math.max(0, 5 - props.review.rating)) }
    : { filled: '', empty: '' }));

const primaryClass =
    'mt-4 inline-flex items-center gap-2 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-primary/90';
</script>

<template>
    <section :class="panel">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <h3 class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ review.heading }}</h3>
            <a v-if="review.hasReview && review.actionLabel" :href="review.actionUrl" class="text-xs font-semibold text-primary hover:underline">
                {{ review.actionLabel }}
            </a>
        </div>

        <template v-if="!review.hasReview">
            <p class="mt-2 text-sm text-slate-600 dark:text-slate-300">{{ review.prompt }}</p>
            <a v-if="review.actionLabel" :href="review.actionUrl" :class="primaryClass">{{ review.actionLabel }}</a>
        </template>

        <template v-else>
            <div class="mt-3 rounded-xl border border-slate-200 p-4 dark:border-slate-800">
                <p class="text-lg text-amber-500">
                    {{ stars.filled }}<span class="text-slate-300 dark:text-slate-600">{{ stars.empty }}</span>
                    <span class="ml-2 align-middle text-xs font-semibold text-slate-400">{{ review.outOfFive }}</span>
                </p>

                <p v-if="review.comment" class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-300">{{ review.comment }}</p>
                <p v-else class="mt-2 text-sm italic text-slate-400">{{ review.noComment }}</p>

                <p class="mt-2 text-xs text-slate-400">{{ review.metaLine }}</p>
            </div>

            <p v-if="review.editableUntil" class="mt-3 text-xs text-slate-500 dark:text-slate-400">{{ review.editableUntil }}</p>
        </template>
    </section>
</template>
