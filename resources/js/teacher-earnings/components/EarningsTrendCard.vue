<script setup>
/**
 * Six months of net earnings, drawn with CSS only — no chart dependency, no
 * canvas, and every bar carries its amount as text so the trend is readable by
 * a screen reader and at any zoom level.
 */
import { computed } from 'vue';
import AppIcon from '../../shared/components/AppIcon.vue';
import UiSectionHeading from '../../shared/components/UiSectionHeading.vue';
import { usePanel } from '../../shared/composables/usePanel';
import { countLabel, useCopy, usePage } from '../composables/usePageContext';

const copy = useCopy();
const page = usePage();
const panel = usePanel();

const trend = computed(() => page.trend ?? { max: 0, totalLabel: '', months: [] });
const hasData = computed(() => Number(trend.value.max) > 0);

const monthLabel = (month) =>
    countLabel(copy.trend?.monthLabel, month.count, {
        month: month.label,
        amount: month.valueLabel,
    });
</script>

<template>
    <section :class="[panel, 'flex flex-col']" :aria-label="copy.trend?.title">
        <UiSectionHeading
            :title="copy.trend?.title ?? ''"
            :description="copy.trend?.subtitle"
            icon="chart"
        >
            <template #action>
                <div v-if="hasData" class="text-right">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400">
                        {{ copy.trend?.totalLabel }}
                    </p>
                    <p class="text-sm font-extrabold tabular-nums text-slate-800 dark:text-slate-100">
                        {{ trend.totalLabel }}
                    </p>
                </div>
            </template>
        </UiSectionHeading>

        <div v-if="hasData" class="mt-6 flex h-40 items-end gap-1.5 sm:gap-2">
            <div
                v-for="month in trend.months"
                :key="month.key"
                class="flex h-full flex-1 flex-col items-center justify-end gap-1.5"
                :title="monthLabel(month)"
            >
                <span class="hidden text-[10px] font-semibold tabular-nums text-slate-400 sm:block">
                    {{ month.valueLabel }}
                </span>

                <div
                    class="w-full rounded-t-md bg-slate-100 dark:bg-slate-800"
                    :style="{ height: `${Math.max(month.percent, 3)}%` }"
                    aria-hidden="true"
                >
                    <div class="h-full w-full rounded-t-md bg-primary/70"></div>
                </div>

                <span class="text-[11px] font-semibold text-slate-500 dark:text-slate-400">
                    {{ month.label }}
                </span>

                <span class="sr-only">{{ monthLabel(month) }}</span>
            </div>
        </div>

        <div
            v-else
            class="mt-4 flex flex-1 flex-col items-center justify-center gap-2 rounded-xl border border-dashed border-slate-300 px-4 py-8 text-center dark:border-slate-700"
        >
            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 p-2 text-slate-400 dark:bg-slate-800 dark:text-slate-500" aria-hidden="true">
                <AppIcon name="chart" />
            </span>
            <p class="max-w-sm text-sm text-slate-500 dark:text-slate-400">{{ copy.trend?.empty }}</p>
        </div>
    </section>
</template>
