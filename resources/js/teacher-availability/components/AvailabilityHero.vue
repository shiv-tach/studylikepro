<script setup>
/**
 * The band above the manager: what the teacher is about to change and what it
 * currently adds up to. The hours chip is live — it follows the grid while the
 * teacher edits, before anything is saved.
 */
import { computed } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';
import { useWeekEditor } from '../../shared/availability/useWeekEditor';
import { format, useCopy, usePage, useUrls } from '../composables/usePageContext';

const copy = useCopy();
const page = usePage();
const urls = useUrls();

const { hoursLabel, daysPerWeek } = useWeekEditor();

const chips = computed(() => [
    { key: 'timezone', icon: 'clock', value: page.timezone },
    {
        key: 'length',
        icon: 'calendar',
        value: format(copy.hero?.chips?.lessonLength ?? '', { minutes: page.pricing?.lessonDuration ?? 60 }),
    },
    { key: 'hours', icon: 'clock', value: format(copy.weeklyHours, { hours: hoursLabel.value }) },
    { key: 'days', icon: 'calendar', value: format(copy.daysCount, { count: daysPerWeek.value }) },
]);
</script>

<template>
    <section
        aria-labelledby="teacher-availability-hero"
        class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-primary via-primary/80 to-purple-600 p-6 text-white shadow-lg shadow-primary/10 dark:shadow-primary/5 sm:p-8"
    >
        <div class="relative z-10 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-2xl">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-white/70">
                    {{ copy.hero?.eyebrow }}
                </p>
                <h1 id="teacher-availability-hero" class="mt-1 text-2xl font-extrabold tracking-tight sm:text-3xl">
                    {{ copy.hero?.title }}
                </h1>
                <p class="mt-2 text-sm leading-relaxed text-white/90">
                    {{ copy.hero?.intro }}
                </p>

                <ul class="mt-4 flex flex-wrap gap-2" :aria-label="copy.hero?.eyebrow">
                    <li
                        v-for="chip in chips"
                        :key="chip.key"
                        class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold backdrop-blur-sm"
                    >
                        <span class="h-3.5 w-3.5 shrink-0" aria-hidden="true"><AppIcon :name="chip.icon" /></span>
                        {{ chip.value }}
                    </li>
                </ul>
            </div>

            <div class="flex shrink-0 flex-col items-start gap-3 sm:items-end">
                <div class="rounded-2xl bg-white/15 px-4 py-3 text-right backdrop-blur-sm">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-white/70">
                        {{ copy.pricing?.rateLabel }}
                    </p>
                    <p class="text-xl font-extrabold">
                        {{ page.pricing?.rateLabel }}
                        <span class="text-xs font-semibold text-white/70">{{ copy.pricing?.perHour }}</span>
                    </p>
                    <p class="mt-1 text-[11px] text-white/80">
                        {{ format(copy.pricing?.ratePreview, {
                            amount: page.pricing?.earningsLabel,
                            percent: page.pricing?.commissionPercent,
                        }) }}
                    </p>
                </div>

                <a
                    :href="urls.schedule"
                    class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-primary shadow-sm transition-transform hover:-translate-y-0.5"
                >
                    <span class="h-4 w-4" aria-hidden="true"><AppIcon name="calendar" /></span>
                    {{ copy.hero?.schedule }}
                </a>
                <p class="text-xs text-white/70">
                    {{ format(copy.hero?.timezone ?? '', { timezone: page.timezone }) }}
                </p>
            </div>
        </div>

        <div class="pointer-events-none absolute -right-6 -top-6 h-36 w-36 rounded-full bg-white/10 blur-xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-10 right-24 h-28 w-28 rounded-full bg-white/10 blur-lg" aria-hidden="true"></div>
    </section>
</template>
