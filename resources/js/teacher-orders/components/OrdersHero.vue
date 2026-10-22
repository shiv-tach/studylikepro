<script setup>
/**
 * The band above the order book: how the whole business is doing — total
 * orders, what is waiting for money, what is running and what is done — plus the
 * payout earned so far. Every figure comes from Blade's grouped counts, so the
 * hero never counts only the current page.
 */
import { computed } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';
import { countLabel, useCopy, usePage } from '../composables/usePageContext';

const copy = useCopy();
const page = usePage();

const stats = computed(() => page.stats ?? {});

const chips = computed(() => {
    if (!stats.value.total) {
        return [];
    }

    return [
        { key: 'total', icon: 'wallet', value: countLabel(copy.stats?.total, stats.value.total) },
        { key: 'awaiting', icon: 'clock', value: `${stats.value.awaiting} ${copy.stats?.awaiting ?? ''}`.trim() },
        { key: 'active', icon: 'play', value: `${stats.value.active} ${copy.stats?.active ?? ''}`.trim() },
        { key: 'completed', icon: 'check-circle', value: `${stats.value.completed} ${copy.stats?.completed ?? ''}`.trim() },
    ];
});
</script>

<template>
    <section
        aria-labelledby="teacher-orders-hero"
        class="relative overflow-hidden rounded-2xl bg-gradient-to-r from-primary via-primary/80 to-purple-600 p-6 text-white shadow-lg shadow-primary/10 dark:shadow-primary/5 sm:p-8"
    >
        <div class="relative z-10 flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
            <div class="max-w-2xl">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-white/70">
                    {{ copy.hero?.eyebrow }}
                </p>
                <h1 id="teacher-orders-hero" class="mt-1 text-2xl font-extrabold tracking-tight sm:text-3xl">
                    {{ copy.hero?.title }}
                </h1>
                <p class="mt-2 text-sm leading-relaxed text-white/90">
                    {{ copy.hero?.intro }}
                </p>

                <ul v-if="chips.length" class="mt-4 flex flex-wrap gap-2" :aria-label="copy.stats?.label">
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

            <div class="flex shrink-0 items-center gap-4 rounded-2xl border border-white/25 bg-white/10 px-5 py-4 backdrop-blur-sm">
                <span class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/15 p-2.5" aria-hidden="true">
                    <AppIcon name="wallet" />
                </span>
                <div>
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-white/70">
                        {{ copy.hero?.earnedLabel }}
                    </p>
                    <p class="mt-0.5 text-xl font-extrabold tracking-tight">{{ stats.earnedLabel }}</p>
                </div>
            </div>
        </div>

        <div class="pointer-events-none absolute -right-6 -top-6 h-36 w-36 rounded-full bg-white/10 blur-xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-10 right-24 h-28 w-28 rounded-full bg-white/10 blur-lg" aria-hidden="true"></div>
    </section>
</template>
