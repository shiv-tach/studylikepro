<script setup>
/**
 * The band above the schedule: what the week holds, and the way to change it.
 * Every figure comes from Blade's own count over the seven-day window, so the
 * hero never counts only the first twenty rows of the agenda.
 */
import { computed } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';
import { useCopy, usePage, useUrls, countLabel, format } from '../composables/usePageContext';

const copy = useCopy();
const page = usePage();
const urls = useUrls();

const stats = computed(() => page.stats ?? {});

const chips = computed(() => {
    const values = stats.value;
    const list = [
        { key: 'today', icon: 'calendar', value: countLabel(copy.stats?.today, values.todayCount ?? 0) },
        { key: 'week', icon: 'clock', value: countLabel(copy.stats?.week, values.weekCount ?? 0) },
    ];

    if (values.weekMinutes) {
        list.push({ key: 'minutes', icon: 'clock', value: format(copy.stats?.minutes, { minutes: values.weekMinutes }) });
    }

    if (values.holdCount) {
        list.push({ key: 'holds', icon: 'alert', value: countLabel(copy.stats?.holds, values.holdCount) });
    }

    return list;
});
</script>

<template>
    <section
        aria-labelledby="teacher-schedule-hero"
        class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-primary via-primary/80 to-purple-600 p-6 text-white shadow-lg shadow-primary/10 dark:shadow-primary/5 sm:p-8"
    >
        <div class="relative z-10 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-2xl">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-white/70">
                    {{ copy.hero?.eyebrow }}
                </p>
                <h1 id="teacher-schedule-hero" class="mt-1 text-2xl font-extrabold tracking-tight sm:text-3xl">
                    {{ copy.hero?.title }}
                </h1>
                <p class="mt-2 text-sm leading-relaxed text-white/90">
                    {{ copy.hero?.intro }}
                </p>

                <ul class="mt-4 flex flex-wrap gap-2" :aria-label="copy.stats?.label">
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
                <a
                    :href="urls.availability"
                    class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-primary shadow-sm transition-transform hover:-translate-y-0.5"
                >
                    <span class="h-4 w-4" aria-hidden="true"><AppIcon name="calendar" /></span>
                    {{ copy.hero?.availability }}
                </a>
                <p class="text-xs text-white/70">{{ copy.hero?.timezone }}</p>
            </div>
        </div>

        <div class="pointer-events-none absolute -right-6 -top-6 h-36 w-36 rounded-full bg-white/10 blur-xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-10 right-24 h-28 w-28 rounded-full bg-white/10 blur-lg" aria-hidden="true"></div>
    </section>
</template>
