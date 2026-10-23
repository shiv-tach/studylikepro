<script setup>
/**
 * The lesson's identity card: who it is with, when it runs, and what it pays.
 * Blade supplies every formatted value, so this band never formats a date.
 */
import AppIcon from '../../shared/components/AppIcon.vue';
import { CHIP } from '../../shared/styles';
import { useCopy } from '../composables/usePageContext';

defineProps({
    header: { type: Object, required: true },
    lesson: { type: Object, required: true },
});

const copy = useCopy();
</script>

<template>
    <section
        class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-primary via-primary/80 to-purple-600 p-6 text-white shadow-lg shadow-primary/10 dark:shadow-primary/5 sm:p-8"
        aria-labelledby="booking-hero-title"
    >
        <div class="relative z-10">
            <a
                :href="header.backUrl"
                class="inline-flex items-center gap-1.5 text-xs font-semibold text-white/75 transition-colors hover:text-white"
            >
                <span class="h-3.5 w-3.5" aria-hidden="true"><AppIcon name="chevron-left" /></span>
                {{ header.backLabel }}
            </a>

            <div class="mt-4 flex flex-col gap-6 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-3">
                        <span
                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-white/15 text-base font-extrabold tracking-wide backdrop-blur-sm"
                            aria-hidden="true"
                        >
                            {{ header.learnerInitials }}
                        </span>

                        <div class="min-w-0">
                            <p class="text-xs font-semibold uppercase tracking-[0.2em] text-white/70">{{ copy.hero?.eyebrow }}</p>
                            <h1 id="booking-hero-title" class="mt-0.5 truncate text-2xl font-extrabold tracking-tight sm:text-3xl">
                                {{ header.learner }}
                            </h1>
                        </div>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center gap-2">
                        <span :class="[CHIP, 'bg-white/15 text-white backdrop-blur-sm']">{{ header.statusLabel }}</span>

                        <span v-if="lesson.learnerGrade" :class="[CHIP, 'bg-white/15 text-white/90 backdrop-blur-sm']">
                            {{ lesson.learnerGrade }}
                        </span>

                        <span v-if="lesson.subject" :class="[CHIP, 'bg-white/15 text-white/90 backdrop-blur-sm']">
                            {{ lesson.subject }}
                        </span>
                    </div>

                    <p class="mt-3 text-sm font-semibold text-white/90">{{ lesson.title }}</p>

                    <p class="mt-2 flex flex-wrap items-center gap-2 text-sm text-white/80">
                        <span class="inline-flex items-center gap-1.5">
                            <span class="h-4 w-4" aria-hidden="true"><AppIcon name="calendar" /></span>
                            {{ lesson.dateLabel }}
                        </span>
                        <span class="text-white/40" aria-hidden="true">·</span>
                        <span class="tabular-nums">{{ lesson.timeRange }}</span>
                        <span class="text-white/40" aria-hidden="true">·</span>
                        <span>{{ lesson.durationLabel }}</span>
                    </p>
                </div>

                <div class="flex shrink-0 flex-col gap-2 lg:items-end">
                    <p class="text-xs font-medium uppercase tracking-wider text-white/60">{{ copy.hero?.payout }}</p>
                    <p class="text-2xl font-extrabold tabular-nums">{{ lesson.payoutLabel }}</p>
                    <p class="text-xs text-white/70">
                        {{ copy.hero?.reference }} {{ lesson.reference }}
                    </p>
                    <p class="text-xs text-white/60">{{ copy.hero?.timezone }}</p>
                </div>
            </div>
        </div>

        <div class="pointer-events-none absolute -right-6 -top-8 h-40 w-40 rounded-full bg-white/10 blur-xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-12 right-28 h-32 w-32 rounded-full bg-white/10 blur-lg" aria-hidden="true"></div>
    </section>
</template>
