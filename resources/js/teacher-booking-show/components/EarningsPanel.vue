<script setup>
/**
 * The teacher's side of the money: what the lesson pays, the platform fee and
 * where the earning currently sits. Blade formats every amount and decides the
 * note; this panel never calculates a payout.
 */
import AppIcon from '../../shared/components/AppIcon.vue';
import { usePanel } from '../../shared/composables/usePanel';
import { useCopy } from '../composables/usePageContext';

defineProps({
    money: { type: Object, required: true },
    urls: { type: Object, default: () => ({}) },
});

const copy = useCopy();
const panel = usePanel();

const NOTE_TONES = {
    amber: 'border-amber-200/80 bg-amber-50 text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300',
    emerald: 'border-emerald-200/80 bg-emerald-50 text-emerald-800 dark:border-emerald-900/40 dark:bg-emerald-950/30 dark:text-emerald-300',
    rose: 'border-rose-200/80 bg-rose-50 text-rose-700 dark:border-rose-900/40 dark:bg-rose-950/30 dark:text-rose-300',
    slate: 'border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-800 dark:bg-slate-900 dark:text-slate-300',
};
</script>

<template>
    <section :class="panel" aria-labelledby="booking-money-title">
        <h2 id="booking-money-title" class="text-sm font-bold text-slate-800 dark:text-slate-100">{{ copy.money?.title }}</h2>

        <dl class="mt-3 space-y-2 text-sm">
            <div class="flex items-center justify-between gap-3">
                <dt class="text-slate-500 dark:text-slate-400">{{ money.priceLabel }}</dt>
                <dd class="font-semibold text-slate-800 dark:text-slate-100">{{ money.price }}</dd>
            </div>

            <div class="flex items-center justify-between gap-3">
                <dt class="text-slate-500 dark:text-slate-400">{{ money.feeLabel }}</dt>
                <dd class="font-semibold text-slate-500 dark:text-slate-400">−{{ money.fee }}</dd>
            </div>

            <div class="flex items-center justify-between gap-3 border-t border-slate-200 pt-2 dark:border-slate-800">
                <dt class="font-semibold text-slate-600 dark:text-slate-300">{{ money.payoutLabel }}</dt>
                <dd class="text-lg font-bold text-slate-900 dark:text-slate-100">{{ money.payout }}</dd>
            </div>
        </dl>

        <p v-if="money.note" class="mt-3 rounded-xl border px-3 py-2 text-xs leading-relaxed" :class="NOTE_TONES[money.noteTone] ?? NOTE_TONES.slate">
            {{ money.note }}
        </p>

        <p v-if="money.purchaseNote" class="mt-2 text-xs leading-relaxed text-slate-500 dark:text-slate-400">
            {{ money.purchaseNote }}
        </p>

        <a
            v-if="urls.earnings"
            :href="urls.earnings"
            class="mt-4 inline-flex items-center gap-1.5 text-xs font-semibold text-primary transition-colors hover:text-primary/80"
        >
            {{ copy.money?.openLedger }}
            <span class="h-3.5 w-3.5" aria-hidden="true"><AppIcon name="arrow-right" /></span>
        </a>
    </section>
</template>
