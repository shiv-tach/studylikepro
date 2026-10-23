<script setup>
/**
 * One review: who left it, the stars, what it was about and the comment. The
 * right-hand action is Blade's call — a review already with support shows the
 * amber chip instead of a report button, because reporting twice is refused.
 */
import { computed } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';
import { usePanel } from '../../shared/composables/usePanel';
import { format, useCopy } from '../composables/usePageContext';
import StarRating from './StarRating.vue';

const props = defineProps({
    review: { type: Object, required: true },
});

const emit = defineEmits(['report']);

const copy = useCopy();
const panel = usePanel();

const initial = computed(() => (props.review.reviewer ?? '?').trim().charAt(0).toUpperCase());

const metaLine = computed(() => [
    props.review.scopeLabel,
    props.review.lessonDate,
    props.review.reviewedLabel,
].filter(Boolean).join(' · '));

const starLabel = computed(() => format(copy.card?.outOfFive, { rating: props.review.rating }));
</script>

<template>
    <article :class="panel">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="flex min-w-0 items-start gap-3">
                <span
                    aria-hidden="true"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-primary/10 text-sm font-bold text-primary"
                >
                    {{ initial }}
                </span>

                <div class="min-w-0">
                    <p class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm font-bold text-slate-800 dark:text-slate-100">
                        <span class="truncate">{{ review.reviewer }}</span>
                        <StarRating
                            :filled="review.stars?.filled"
                            :empty="review.stars?.empty"
                            :label="starLabel"
                        />
                    </p>

                    <p class="mt-0.5 text-xs text-slate-500 dark:text-slate-400">
                        {{ metaLine }}
                        <span
                            v-if="review.edited"
                            class="ml-1 inline-flex items-center rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-500 dark:bg-slate-800 dark:text-slate-400"
                        >
                            {{ copy.card?.edited }}
                        </span>
                    </p>
                </div>
            </div>

            <span
                v-if="review.flagged"
                class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold text-amber-700 dark:bg-amber-900/30 dark:text-amber-400"
                :title="copy.card?.reportedTitle"
            >
                <span class="h-3 w-3 shrink-0" aria-hidden="true"><AppIcon name="shield" /></span>
                {{ copy.card?.reportedChip }}
            </span>

            <button
                v-else-if="review.flagUrl"
                type="button"
                class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-600 transition-colors hover:bg-slate-50 focus:outline-none focus-visible:ring-2 focus-visible:ring-primary/40 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800"
                @click="emit('report', review)"
            >
                <span class="h-3 w-3 shrink-0" aria-hidden="true"><AppIcon name="shield" /></span>
                {{ copy.card?.reportCta }}
            </button>
        </div>

        <p v-if="review.comment" class="mt-4 text-sm leading-relaxed text-slate-600 dark:text-slate-300">
            {{ review.comment }}
        </p>
        <p v-else class="mt-4 text-sm italic text-slate-400">{{ copy.card?.noComment }}</p>
    </article>
</template>
