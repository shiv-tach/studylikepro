<script setup>
/**
 * What other students said: the average, the star distribution and the most
 * recent visible reviews. Students pay for lessons, so this panel never shows
 * a review the moderation team has hidden — Blade only sends the visible ones.
 */
import { usePanel } from '../../shared/composables/usePanel';
import UiChip from '../../shared/components/UiChip.vue';
import UiSectionHeading from '../../shared/components/UiSectionHeading.vue';
import { useCopy } from '../composables/usePageContext';

defineProps({
    reviews: { type: Object, required: true },
});

const copy = useCopy();
const panel = usePanel();
</script>

<template>
    <section :class="panel">
        <UiSectionHeading :title="copy.reviewsTitle" icon="star">
            <template #action>
                <UiChip v-if="reviews.rating" tone="amber" icon="star">
                    {{ reviews.rating }} · {{ reviews.summaryLabel }}
                </UiChip>
            </template>
        </UiSectionHeading>

        <p
            v-if="!reviews.count"
            class="mt-4 rounded-xl border border-dashed border-slate-300 px-4 py-6 text-center text-sm text-slate-500 dark:border-slate-700 dark:text-slate-400"
        >
            {{ copy.noReviews }}
        </p>

        <template v-else>
            <div class="mt-4 max-w-3xl space-y-2">
                <div v-for="row in reviews.breakdown" :key="row.label" class="flex items-center gap-3 text-xs">
                    <span class="w-8 shrink-0 font-semibold text-slate-500 dark:text-slate-400">{{ row.label }}</span>
                    <div class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
                        <div class="h-full rounded-full bg-amber-400" :style="{ width: `${row.percent}%` }"></div>
                    </div>
                    <span class="w-6 shrink-0 text-right text-slate-400">{{ row.count }}</span>
                </div>
            </div>

            <div class="mt-5 grid gap-4 border-t border-slate-200/80 pt-5 dark:border-slate-800 xl:grid-cols-2">
                <article
                    v-for="review in reviews.items"
                    :key="review.id"
                    class="rounded-xl border border-slate-200/80 p-4 dark:border-slate-800"
                >
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ review.reviewer }}</p>
                        <p class="text-sm text-amber-500">
                            <span aria-hidden="true">
                                {{ review.stars.filled }}<span class="text-slate-300 dark:text-slate-600">{{ review.stars.empty }}</span>
                            </span>
                            <span class="sr-only">{{ (copy.outOfFive ?? '').replace(':rating', review.rating) }}</span>
                        </p>
                    </div>

                    <p v-if="review.comment" class="mt-2 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
                        {{ review.comment }}
                    </p>

                    <p class="mt-2 text-xs text-slate-400">{{ review.meta }}</p>
                </article>
            </div>

            <p v-if="reviews.showingLabel" class="mt-4 text-xs text-slate-400">{{ reviews.showingLabel }}</p>
        </template>
    </section>
</template>
