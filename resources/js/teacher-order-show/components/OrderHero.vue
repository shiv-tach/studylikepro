<script setup>
/**
 * The order at a glance: the service that was bought, who bought it, where it
 * stands and what it pays the teacher. Everything arrives formatted from Blade;
 * the back link is the only navigation this band owns.
 */
import AppIcon from '../../shared/components/AppIcon.vue';
import { useCopy, usePage } from '../composables/usePageContext';

const copy = useCopy();
const page = usePage();
</script>

<template>
    <section
        aria-labelledby="teacher-order-hero"
        class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-primary via-primary/80 to-purple-600 p-6 text-white shadow-lg shadow-primary/10 dark:shadow-primary/5 sm:p-8"
    >
        <div class="relative z-10 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-2xl">
                <a
                    :href="page.urls?.index"
                    class="inline-flex items-center gap-1 text-xs font-semibold text-white/70 transition-colors hover:text-white"
                >
                    <span class="h-3.5 w-3.5" aria-hidden="true"><AppIcon name="chevron-left" /></span>
                    {{ copy.back }}
                </a>

                <p class="mt-3 flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-white/70">
                    <span class="h-4 w-4" aria-hidden="true"><AppIcon :name="page.order?.typeIcon" /></span>
                    {{ page.order?.typeLabel }}
                </p>

                <h1 id="teacher-order-hero" class="mt-1 text-2xl font-extrabold tracking-tight sm:text-3xl">
                    {{ page.order?.scopeTitle }}
                </h1>

                <p class="mt-2 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-white/90">
                    <span class="font-semibold">{{ page.order?.studentName }}</span>
                    <span class="text-white/50" aria-hidden="true">·</span>
                    <span>{{ page.order?.reference }}</span>
                    <span class="text-white/50" aria-hidden="true">·</span>
                    <span>{{ page.order?.orderedLabel }}</span>
                </p>

                <div class="mt-4 flex flex-wrap items-center gap-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold backdrop-blur-sm">
                        {{ page.order?.statusLabel }}
                    </span>
                    <span
                        v-if="page.order?.sessionsCount"
                        class="inline-flex items-center gap-1.5 rounded-full bg-white/15 px-3 py-1 text-xs font-semibold backdrop-blur-sm"
                    >
                        <span class="h-3.5 w-3.5" aria-hidden="true"><AppIcon name="calendar" /></span>
                        {{ page.order?.sessionsLabel }}
                    </span>
                </div>
            </div>

            <div class="flex shrink-0 flex-col items-start gap-2 rounded-2xl border border-white/25 bg-white/10 px-5 py-4 backdrop-blur-sm">
                <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70">
                    {{ page.money?.payout?.label }}
                </p>
                <p class="text-2xl font-extrabold tracking-tight">{{ page.money?.payout?.value }}</p>
            </div>
        </div>

        <div class="pointer-events-none absolute -right-6 -top-6 h-36 w-36 rounded-full bg-white/10 blur-xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-10 right-24 h-28 w-28 rounded-full bg-white/10 blur-lg" aria-hidden="true"></div>
    </section>
</template>
