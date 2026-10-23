<script setup>
/**
 * The 5 → 1 star distribution. Blade computes every percentage from the visible
 * reviews, so the bars can never disagree with the average above them, and the
 * bars are decoration — the count beside each row carries the same fact.
 */
import { computed } from 'vue';
import UiChip from '../../shared/components/UiChip.vue';
import UiSectionHeading from '../../shared/components/UiSectionHeading.vue';
import { usePanel } from '../../shared/composables/usePanel';
import { countLabel, format, useCopy, usePage } from '../composables/usePageContext';

const copy = useCopy();
const page = usePage();
const panel = usePanel();

const breakdown = computed(() => page.breakdown ?? { total: 0, rows: [] });
const total = computed(() => Number(breakdown.value.total ?? 0));
</script>

<template>
    <section :class="panel">
        <UiSectionHeading :title="copy.breakdown?.title" icon="star">
            <template #action>
                <UiChip v-if="total" tone="slate">{{ countLabel(copy.breakdown?.countLabel, total) }}</UiChip>
            </template>
        </UiSectionHeading>

        <p v-if="!total" class="mt-3 text-sm text-slate-500 dark:text-slate-400">
            {{ copy.breakdown?.empty }}
        </p>

        <div v-else class="mt-4 space-y-2">
            <div v-for="row in breakdown.rows" :key="row.star" class="flex items-center gap-3 text-sm">
                <span class="w-10 shrink-0 font-semibold text-slate-500 dark:text-slate-400">{{ row.label }}</span>

                <div
                    class="h-2 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
                    role="img"
                    :aria-label="format(copy.breakdown?.percentLabel, { percent: row.percent })"
                >
                    <div
                        class="h-full rounded-full bg-amber-400 transition-[width] duration-500 ease-out"
                        :style="{ width: `${row.percent}%` }"
                    ></div>
                </div>

                <span class="w-8 shrink-0 text-right text-xs text-slate-400">{{ row.count }}</span>
            </div>
        </div>
    </section>
</template>
