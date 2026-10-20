<script setup>
import { computed } from 'vue';
import AppIcon from '../../../landing/components/AppIcon.vue';

const props = defineProps({
    reviews: { type: Object, required: true },
    copy: { type: Object, required: true },
});

const hasReviews = computed(() => (props.reviews.count ?? 0) > 0);
</script>

<template>
    <section id="reviews" class="hairline rounded-2xl bg-white p-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-display text-xl font-semibold">{{ copy.reviewsTitle }}</h2>

            <span
                v-if="hasReviews"
                class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-bold text-amber-700"
            >
                ★ {{ reviews.rating }} · {{ reviews.summaryLabel }}
            </span>
        </div>

        <p
            v-if="!hasReviews"
            class="mt-4 rounded-xl border border-dashed border-line px-4 py-8 text-center text-sm text-ink-500"
        >
            {{ copy.noReviews }}
        </p>

        <template v-else>
            <div class="mt-4 flex flex-wrap items-center gap-4">
                <div class="text-center">
                    <p class="font-display text-4xl font-semibold leading-none">{{ reviews.rating }}</p>
                    <p class="mt-1 text-[11px] font-semibold text-ink-500">{{ copy.outOfFive }}</p>
                </div>

                <div class="min-w-[12rem] flex-1 space-y-1.5">
                    <div v-for="row in reviews.breakdown" :key="row.label" class="flex items-center gap-2 text-[11px]">
                        <span class="w-7 shrink-0 font-semibold text-ink-500">{{ row.label }}</span>
                        <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-ink-50">
                            <div class="h-full rounded-full bg-amber-400" :style="{ width: `${row.percent}%` }"></div>
                        </div>
                        <span class="w-5 shrink-0 text-right text-ink-400">{{ row.count }}</span>
                    </div>
                </div>
            </div>

            <ul class="mt-5 space-y-3 border-t border-line pt-5">
                <li v-for="review in reviews.items" :key="review.id" class="rounded-xl bg-ink-50 p-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <p class="text-sm font-semibold text-ink">{{ review.reviewer }}</p>
                        <p class="text-sm text-amber-500">
                            {{ review.stars.filled }}<span class="text-ink-400/60">{{ review.stars.empty }}</span>
                        </p>
                    </div>

                    <p v-if="review.comment" class="mt-2 text-sm leading-relaxed text-ink-700">{{ review.comment }}</p>

                    <p v-if="review.meta" class="mt-2 flex items-center gap-1.5 text-[11px] text-ink-400">
                        <AppIcon name="check" class="h-3 w-3" />
                        {{ review.meta }}
                    </p>
                </li>
            </ul>

            <p v-if="reviews.showingLabel" class="mt-3 text-[11px] text-ink-400">{{ reviews.showingLabel }}</p>
        </template>
    </section>
</template>
