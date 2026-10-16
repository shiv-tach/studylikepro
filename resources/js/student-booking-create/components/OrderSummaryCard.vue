<script setup>
/**
 * The sticky order summary: who and what the lesson is for, how the total is
 * built and the cancellation promise. Every figure and label arrives
 * pre-formatted from Blade, so the card never computes a price itself.
 */
import { usePanel } from '../../shared/composables/usePanel';
import { useCopy } from '../composables/usePageContext';

defineProps({
    summary: { type: Object, required: true },
});

const copy = useCopy();
const panel = usePanel();

const rowLabel = 'text-slate-500 dark:text-slate-400';
const rowValue = 'font-semibold text-slate-800 dark:text-slate-100';
</script>

<template>
    <aside class="space-y-4 lg:sticky lg:top-24 lg:self-start">
        <div :class="panel">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-400">{{ copy.summaryTitle }}</p>

            <div class="mt-3 space-y-2 text-sm">
                <div class="flex items-center justify-between gap-3">
                    <span :class="rowLabel">{{ copy.teacher }}</span>
                    <span :class="rowValue">{{ summary.teacher }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span :class="rowLabel">{{ copy.subject }}</span>
                    <span :class="rowValue">{{ summary.subject }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span :class="rowLabel">{{ copy.length }}</span>
                    <span :class="rowValue">{{ summary.length }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span :class="rowLabel">{{ copy.rate }}</span>
                    <span :class="rowValue">{{ summary.rate }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span :class="rowLabel">{{ summary.lessonLabel }}</span>
                    <span :class="rowValue">{{ summary.lessonPrice }}</span>
                </div>
                <div class="flex items-center justify-between gap-3">
                    <span :class="rowLabel">{{ copy.bookingFee }}</span>
                    <span :class="['font-semibold', summary.feeHasDiscount ? 'text-emerald-600 dark:text-emerald-400' : rowValue]">
                        <template v-if="summary.feeIsFree">
                            {{ summary.feeFreeLabel }}
                            <span class="text-xs font-normal text-slate-400 line-through">{{ summary.feeGross }}</span>
                        </template>
                        <template v-else-if="summary.feeHasDiscount">
                            {{ summary.feeValue }}
                            <span class="text-xs font-normal text-slate-400 line-through">{{ summary.feeGross }}</span>
                        </template>
                        <template v-else>{{ summary.feeValue }}</template>
                    </span>
                </div>
            </div>

            <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-200 pt-4 dark:border-slate-800">
                <span class="text-sm font-semibold text-slate-600 dark:text-slate-300">{{ summary.youPay }}</span>
                <span class="text-xl font-extrabold tracking-tight text-slate-900 dark:text-slate-100">{{ summary.total }}</span>
            </div>

            <p
                v-if="summary.promotion"
                class="mt-3 rounded-xl bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-700 dark:bg-emerald-950/30 dark:text-emerald-400"
            >
                {{ summary.promotion }}
            </p>

            <p class="mt-3 text-xs leading-relaxed text-slate-500 dark:text-slate-400">{{ summary.cancelNotice }}</p>
        </div>
    </aside>
</template>
