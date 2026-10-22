<script setup>
/**
 * What the order is worth: the service price, any booking fee the student paid,
 * the platform commission and the payout that lands with the teacher. The
 * receipt line and the earning state come straight from Blade, so a pending
 * payout never reads as available money.
 */
import { usePanel } from '../../shared/composables/usePanel';
import { HEADING_TEXT } from '../../shared/styles';
import AppIcon from '../../shared/components/AppIcon.vue';
import UiChip from '../../shared/components/UiChip.vue';
import { useCopy } from '../composables/usePageContext';

defineProps({
    money: { type: Object, required: true },
});

const copy = useCopy();
const panel = usePanel();
</script>

<template>
    <section :class="panel" aria-labelledby="teacher-order-money-heading">
        <div class="flex items-start justify-between gap-3">
            <h2 id="teacher-order-money-heading" :class="['text-sm font-bold', HEADING_TEXT]">
                {{ copy.panels?.money }}
            </h2>

            <UiChip v-if="money.earning" :tone="money.earning.tone">{{ money.earning.label }}</UiChip>
        </div>

        <dl class="mt-4 space-y-2 text-sm">
            <div v-for="row in money.rows" :key="row.key" class="flex items-center justify-between gap-3">
                <dt class="text-slate-500 dark:text-slate-400">{{ row.label }}</dt>
                <dd
                    class="tabular-nums font-semibold"
                    :class="row.muted ? 'text-slate-500 dark:text-slate-400' : 'text-slate-800 dark:text-slate-100'"
                >
                    {{ row.value }}
                </dd>
            </div>
        </dl>

        <div class="mt-4 flex items-center justify-between gap-3 border-t border-slate-200 pt-4 dark:border-slate-800">
            <p class="flex items-center gap-2 text-sm font-semibold text-slate-600 dark:text-slate-300">
                <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary/10 p-1.5 text-primary" aria-hidden="true">
                    <AppIcon name="wallet" />
                </span>
                {{ money.payout.label }}
            </p>
            <p class="text-lg font-extrabold tabular-nums tracking-tight text-primary">{{ money.payout.value }}</p>
        </div>

        <p v-if="money.payment" class="mt-3 text-xs text-slate-400">{{ money.payment.label }}</p>
        <p class="mt-2 text-xs leading-relaxed text-slate-400">{{ copy.money?.feeNote }}</p>
    </section>
</template>
