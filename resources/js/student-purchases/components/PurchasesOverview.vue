<script setup>
/**
 * The wallet summary above the list: the figures from the student's whole
 * purchase history, so the list itself can stay a plain grid of cards.
 */
import { usePanel } from '../../shared/composables/usePanel';
import AppIcon from '../../shared/components/AppIcon.vue';
import { useCopy } from '../composables/usePageContext';

defineProps({
    stats: { type: Array, default: () => [] },
});

const copy = useCopy();
const panel = usePanel();

// Semantic icon tones, so a preset's accent colour never leaks into a tile.
const toneClasses = {
    primary: 'bg-primary/10 text-primary',
    amber: 'bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400',
    emerald: 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400',
    slate: 'bg-slate-100 text-slate-500 dark:bg-slate-800 dark:text-slate-400',
};
</script>

<template>
    <section :class="panel" aria-labelledby="purchases-overview">
        <h2 id="purchases-overview" class="text-base font-bold text-slate-800 dark:text-slate-100">
            {{ copy.overviewTitle }}
        </h2>
        <p class="mt-1 text-sm text-slate-500 dark:text-slate-400">{{ copy.overviewIntro }}</p>

        <div class="mt-4 grid gap-3 sm:grid-cols-3">
            <div
                v-for="stat in stats"
                :key="stat.key"
                class="flex items-center gap-3 rounded-xl border border-slate-200/80 p-3 dark:border-slate-800/80"
            >
                <span
                    :class="[toneClasses[stat.tone] ?? toneClasses.slate, 'flex h-9 w-9 shrink-0 items-center justify-center rounded-xl p-2']"
                >
                    <AppIcon :name="stat.icon" />
                </span>
                <div class="min-w-0">
                    <p class="text-lg font-bold leading-tight tabular-nums text-slate-800 dark:text-slate-100">
                        {{ stat.value }}
                    </p>
                    <p class="truncate text-xs text-slate-500 dark:text-slate-400">{{ stat.label }}</p>
                </div>
            </div>
        </div>
    </section>
</template>
