<script setup>
/**
 * Who this teacher is: the avatar, the headline, the trust signals, the bio and
 * the three facts a student compares between teachers. Every figure and label
 * arrives pre-formatted from Blade, so the card never guesses a rate.
 */
import { usePanel } from '../../shared/composables/usePanel';
import UiChip from '../../shared/components/UiChip.vue';
import { useCopy } from '../composables/usePageContext';

defineProps({
    teacher: { type: Object, required: true },
});

const copy = useCopy();
const panel = usePanel();
</script>

<template>
    <article :class="panel">
        <div class="flex flex-wrap items-start gap-4">
            <img
                v-if="teacher.avatarUrl"
                :src="teacher.avatarUrl"
                :alt="teacher.name"
                class="h-20 w-20 rounded-2xl object-cover"
            >
            <span
                v-else
                class="flex h-20 w-20 shrink-0 items-center justify-center rounded-2xl bg-primary/10 text-2xl font-bold text-primary"
                aria-hidden="true"
            >
                {{ teacher.initials }}
            </span>

            <div class="min-w-0 flex-1">
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-2xl font-extrabold tracking-tight text-slate-800 dark:text-slate-100">
                        {{ teacher.name }}
                    </h1>
                    <UiChip tone="emerald" icon="check-circle">{{ copy.verified }}</UiChip>
                    <UiChip v-if="teacher.governmentBadge" tone="primary">{{ copy.governmentTeacher }}</UiChip>
                </div>

                <p v-if="teacher.headline" class="mt-1 text-slate-600 dark:text-slate-400">{{ teacher.headline }}</p>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <UiChip v-if="teacher.rating" tone="amber" icon="star">
                        {{ teacher.rating }}
                        <span class="font-medium text-amber-600/80 dark:text-amber-400/80">
                            ({{ teacher.reviewsLabel }})
                        </span>
                    </UiChip>
                    <UiChip v-else>{{ copy.noReviewsYet }}</UiChip>

                    <UiChip>{{ teacher.lessonsTaught }}</UiChip>
                    <UiChip>{{ teacher.experience }}</UiChip>
                    <UiChip v-if="teacher.rateChip" tone="primary">{{ teacher.rateChip }}</UiChip>
                </div>
            </div>
        </div>

        <p v-if="teacher.bio" class="mt-5 text-sm leading-relaxed text-slate-600 dark:text-slate-400">
            {{ teacher.bio }}
        </p>

        <dl class="mt-5 grid gap-4 border-t border-slate-200/80 pt-5 text-sm dark:border-slate-800 sm:grid-cols-3">
            <div v-for="fact in teacher.facts" :key="fact.label">
                <dt class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ fact.label }}</dt>
                <dd class="mt-1 text-slate-700 dark:text-slate-300">{{ fact.value }}</dd>
            </div>
        </dl>
    </article>
</template>
